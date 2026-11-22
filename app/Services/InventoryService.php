<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function location(int $shopId, string $code, ?int $branchId = null): InventoryLocation
    {
        $branchId ??= app(BranchContext::class)->id($shopId);
        if ($code === InventoryLocation::Outlet && $branchId !== null) {
            $existing = InventoryLocation::where('shop_id', $shopId)->where('branch_id', $branchId)->first();
            if ($existing) {
                return $existing;
            }

            $branchName = Branch::where('shop_id', $shopId)->whereKey($branchId)->value('name');

            return InventoryLocation::create([
                'shop_id' => $shopId,
                'branch_id' => $branchId,
                'code' => 'outlet-'.$branchId,
                'name' => $branchName ?: 'Outlet Cabang',
            ]);
        }

        return InventoryLocation::firstOrCreate(
            ['shop_id' => $shopId, 'code' => $code],
            ['name' => match ($code) {
                InventoryLocation::Warehouse => 'Gudang Pusat',
                InventoryLocation::Kitchen => 'Central Kitchen',
                InventoryLocation::Outlet => 'Outlet Utama',
            }],
        );
    }

    public function quantity(RawMaterial $material, InventoryLocation $location): float
    {
        $quantity = $location->balances()->where('raw_material_id', $material->id)->lockForUpdate()->value('quantity');

        return (float) ($quantity ?? ($location->code === InventoryLocation::Outlet ? $material->current_stock : 0));
    }

    /**
     * Record a movement inside the caller's transaction while it holds the material lock.
     */
    public function move(RawMaterial $material, InventoryLocation $location, float $change, string $type, string $reference, ?int $referenceId, string $notes, ?int $userId, ?string $unitCost = null): void
    {
        abort_unless((int) $material->shop_id === (int) $location->shop_id, 404);
        $quantity = round($this->quantity($material, $location) + $change, 6);
        if (abs($quantity) > 999999999.999999) {
            throw ValidationException::withMessages(['amount' => 'Saldo stok melampaui batas penyimpanan.']);
        }

        InventoryBalance::upsert(
            [['inventory_location_id' => $location->id, 'raw_material_id' => $material->id, 'quantity' => $quantity]],
            ['inventory_location_id', 'raw_material_id'],
            ['quantity'],
        );
        if ($location->code === InventoryLocation::Outlet) {
            $material->update(['current_stock' => $quantity]);
        }

        StockMutation::create([
            'shop_id' => $material->shop_id,
            'inventory_location_id' => $location->id,
            ...($location->branch_id ? ['branch_id' => $location->branch_id] : []),
            'raw_material_id' => $material->id,
            'unit' => $material->unit,
            'unit_cost' => $unitCost ?? $material->cost_per_unit,
            'type' => $type,
            'amount' => $change,
            'balance_after' => $quantity,
            'reference_type' => $reference,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'user_id' => $userId,
        ]);
    }

    public function deductStockForOrder(Order $order, ?int $batchNo = null): void
    {
        DB::transaction(function () use ($order, $batchNo): void {
            $lockedOrder = Order::where('shop_id', $order->shop_id)->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $items = $lockedOrder->items()->whereNull('stock_posted_at')
                ->where('status', '!=', 'cancelled')
                ->when($batchNo !== null, fn ($query) => $query->where('batch_no', $batchNo))
                ->with('product.recipes')->get();
            if ($items->isEmpty()) {
                return;
            }

            $modifierIds = $items->flatMap(fn ($item) => array_column($item->modifiers ?? [], 'id'))->unique();
            $modifiers = Modifier::whereIn('id', $modifierIds)
                ->whereHas('group', fn ($query) => $query->where('shop_id', $order->shop_id))
                ->with('ingredients')->get()->keyBy('id');

            $amounts = [];
            foreach ($items as $item) {
                foreach ($item->product?->recipes ?? [] as $recipe) {
                    $amounts[$recipe->raw_material_id] = ($amounts[$recipe->raw_material_id] ?? 0) + (float) $recipe->amount * $item->quantity;
                }
                foreach (array_unique(array_column($item->modifiers ?? [], 'id')) as $modifierId) {
                    foreach ($modifiers->get($modifierId)?->ingredients ?? [] as $material) {
                        $amounts[$material->id] = ($amounts[$material->id] ?? 0) + (float) $material->pivot->amount * $item->quantity;
                    }
                }
            }

            if ($amounts !== []) {
                $location = $this->location($order->shop_id, InventoryLocation::Outlet, $lockedOrder->branch_id);
                $materials = RawMaterial::where('shop_id', $order->shop_id)->whereIn('id', array_keys($amounts))
                    ->orderBy('id')->lockForUpdate()->get();
                if ($materials->count() !== count($amounts)) {
                    throw ValidationException::withMessages(['items' => 'Bahan resep pesanan tidak tersedia.']);
                }
                foreach ($materials as $material) {
                    $used = round($amounts[$material->id], 6);
                    if ($this->quantity($material, $location) < $used) {
                        throw ValidationException::withMessages(['items' => 'Stok outlet tidak cukup untuk '.$material->name.'.']);
                    }
                    $this->move($material, $location, -$used, 'out_order', 'Order', $order->id, "Pesanan #{$order->id}", auth()->id());
                }
            }

            $lockedOrder->items()->whereKey($items->modelKeys())->update(['stock_posted_at' => now()]);
        }, 3);
    }

    /**
     * Reverse a whole order before preparation. The caller holds the order lock.
     */
    public function reverseUnpreparedOrder(Order $order, int $userId): void
    {
        if (! in_array($order->order_status, ['CONFIRMED', 'HOLD'], true)
            || $order->items()->whereIn('status', ['cooking', 'ready', 'completed'])->exists()) {
            return;
        }

        $mutations = StockMutation::where('shop_id', $order->shop_id)->where('reference_id', $order->id)
            ->whereIn('reference_type', ['Order', 'OrderCancellation'])->get();
        if ($mutations->contains('reference_type', 'OrderCancellation')) {
            return;
        }

        foreach ($mutations->sortBy('raw_material_id') as $mutation) {
            $material = RawMaterial::withTrashed()->where('shop_id', $order->shop_id)
                ->whereKey($mutation->raw_material_id)->lockForUpdate()->firstOrFail();
            $location = InventoryLocation::where('shop_id', $order->shop_id)
                ->whereKey($mutation->inventory_location_id)->firstOrFail();
            $this->move($material, $location, -(float) $mutation->amount, 'in', 'OrderCancellation', $order->id,
                "Pembatalan pesanan #{$order->id}", $userId, $mutation->unit_cost);
        }
    }

    public function syncProductHpp(Product $product): float
    {
        $calculatedHpp = $product->calculateHppFromRecipes();
        $product->update(['cogs' => $calculatedHpp]);

        return $calculatedHpp;
    }
}
