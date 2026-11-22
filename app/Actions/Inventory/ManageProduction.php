<?php

namespace App\Actions\Inventory;

use App\Models\ActivityLog;
use App\Models\InventoryLocation;
use App\Models\ProductionRecipe;
use App\Models\ProductionWorkOrder;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageProduction
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @param  array{request_id: string, output_material_id: int, yield_quantity: numeric, notes?: ?string, items: array<array{raw_material_id: int, quantity: numeric}>}  $data
     */
    public function createRecipe(User $user, array $data): ProductionRecipe
    {
        return DB::transaction(function () use ($user, $data): ProductionRecipe {
            $shop = Shop::whereKey($user->shop_id)->lockForUpdate()->firstOrFail();
            $existing = ProductionRecipe::where('shop_id', $shop->id)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                return $existing;
            }

            $output = RawMaterial::where('shop_id', $shop->id)->whereKey($data['output_material_id'])->firstOrFail();
            $ingredientIds = array_column($data['items'], 'raw_material_id');
            if (in_array($output->id, array_map('intval', $ingredientIds), true)) {
                throw ValidationException::withMessages(['items' => 'Bahan hasil produksi tidak boleh dipakai dalam resepnya sendiri.']);
            }
            $ingredients = RawMaterial::where('shop_id', $shop->id)->whereIn('id', $ingredientIds)->get()->keyBy('id');
            abort_unless($ingredients->count() === count($ingredientIds), 404);

            $latest = ProductionRecipe::where('shop_id', $shop->id)->where('output_material_id', $output->id)->max('version');
            $recipe = ProductionRecipe::create([
                'shop_id' => $shop->id, 'output_material_id' => $output->id, 'request_id' => $data['request_id'],
                'version' => (int) $latest + 1, 'yield_quantity' => $data['yield_quantity'], 'unit' => $output->unit,
                'notes' => $data['notes'] ?? null, 'created_by' => $user->id,
            ]);
            $recipe->items()->createMany(array_map(fn (array $item) => [
                'raw_material_id' => $item['raw_material_id'], 'quantity' => $item['quantity'],
                'unit' => $ingredients[$item['raw_material_id']]->unit,
            ], $data['items']));
            $this->audit($user, 'recipe_created', $recipe, "Menyimpan resep {$output->name} versi {$recipe->version}");

            return $recipe;
        }, 3);
    }

    public function createWorkOrder(User $user, int $recipeId, float $target, string $requestId, ?string $notes): ProductionWorkOrder
    {
        return DB::transaction(function () use ($user, $recipeId, $target, $requestId, $notes): ProductionWorkOrder {
            $shop = Shop::whereKey($user->shop_id)->lockForUpdate()->firstOrFail();
            $existing = ProductionWorkOrder::where('shop_id', $shop->id)->where('request_id', $requestId)->first();
            if ($existing) {
                return $existing;
            }

            $recipe = ProductionRecipe::where('shop_id', $shop->id)->with('items')->whereKey($recipeId)->firstOrFail();
            $kitchen = $this->inventory->location($shop->id, InventoryLocation::Kitchen);
            $order = ProductionWorkOrder::create([
                'shop_id' => $shop->id, 'production_recipe_id' => $recipe->id, 'inventory_location_id' => $kitchen->id,
                'request_id' => $requestId, 'status' => ProductionWorkOrder::Planned, 'target_quantity' => $target,
                'notes' => $notes, 'created_by' => $user->id,
            ]);
            foreach ($recipe->items as $item) {
                $planned = round((float) $item->quantity * $target / (float) $recipe->yield_quantity, 6);
                if ($planned <= 0 || $planned > 999999999.999999) {
                    throw ValidationException::withMessages(['target_quantity' => 'Target menghasilkan takaran bahan di luar batas stok.']);
                }
                $order->items()->create([
                    'raw_material_id' => $item->raw_material_id, 'unit' => $item->unit, 'planned_quantity' => $planned,
                ]);
            }
            $this->audit($user, 'work_order_created', $order, 'Membuat surat kerja #'.$order->id);

            return $order;
        }, 3);
    }

    /**
     * @param  array{revision: int, good_quantity: numeric, waste_quantity: numeric, waste_reason?: ?string, items: array<array{work_order_item_id: int, actual_quantity: numeric}>}  $data
     */
    public function recordResult(User $user, int $orderId, array $data): void
    {
        DB::transaction(function () use ($user, $orderId, $data): void {
            $order = $this->lockOrder($user, $orderId);
            $good = (float) $data['good_quantity'];
            $waste = (float) $data['waste_quantity'];
            $reason = $data['waste_reason'] ?? null;
            $actual = collect($data['items'])->mapWithKeys(fn (array $item) => [
                (int) $item['work_order_item_id'] => (float) $item['actual_quantity'],
            ]);
            $items = $order->items()->get();

            if ($order->status === ProductionWorkOrder::Posted) {
                throw ValidationException::withMessages(['result' => 'Hasil yang sudah dibukukan tidak dapat diubah.']);
            }
            if ($items->count() !== $actual->count() || $actual->keys()->diff($items->modelKeys())->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Isi penggunaan aktual untuk semua bahan surat kerja ini.']);
            }
            if ($good + $waste <= 0 || $actual->sum() <= 0) {
                throw ValidationException::withMessages(['result' => 'Catat pemakaian bahan dan hasil layak pakai atau waste.']);
            }
            if ($waste > 0 && blank($reason)) {
                throw ValidationException::withMessages(['waste_reason' => 'Isi alasan waste.']);
            }

            $unchanged = $order->status === ProductionWorkOrder::Review
                && (float) $order->good_quantity === $good && (float) $order->waste_quantity === $waste
                && $order->waste_reason === $reason
                && $items->every(fn ($item) => (float) $item->actual_quantity === $actual[$item->id]);
            if ($unchanged) {
                return;
            }
            $this->checkRevision($order, (int) $data['revision']);
            foreach ($items as $item) {
                $item->update(['actual_quantity' => $actual[$item->id]]);
            }
            $order->update([
                'status' => ProductionWorkOrder::Review, 'good_quantity' => $good, 'waste_quantity' => $waste,
                'waste_reason' => $reason, 'result_revision' => $order->result_revision + 1,
                'recorded_by' => $user->id, 'recorded_at' => now(),
            ]);
            $this->audit($user, 'result_recorded', $order, 'Mencatat hasil surat kerja #'.$order->id);
        }, 3);
    }

    public function postResult(User $user, int $orderId, int $revision): void
    {
        DB::transaction(function () use ($user, $orderId, $revision): void {
            $order = $this->lockOrder($user, $orderId);
            $this->checkRevision($order, $revision);
            if ($order->status === ProductionWorkOrder::Posted) {
                return;
            }
            if ($order->status !== ProductionWorkOrder::Review) {
                throw ValidationException::withMessages(['result' => 'Catat hasil produksi sebelum validasi.']);
            }

            $recipe = $order->recipe;
            $items = $order->items()->orderBy('raw_material_id')->get();
            $materials = RawMaterial::where('shop_id', $user->shop_id)
                ->whereIn('id', $items->pluck('raw_material_id')->push($recipe->output_material_id))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $output = $materials->get($recipe->output_material_id);
            if (! $output || strcasecmp($output->unit, $recipe->unit) !== 0) {
                throw ValidationException::withMessages(['result' => 'Bahan hasil diarsipkan atau satuannya berubah sejak resep dibuat.']);
            }

            $kitchen = $order->location;
            $totalCost = 0.0;
            foreach ($items as $item) {
                $material = $materials->get($item->raw_material_id);
                if (! $material || strcasecmp($material->unit, $item->unit) !== 0) {
                    throw ValidationException::withMessages(['items' => 'Bahan produksi diarsipkan atau satuannya berubah.']);
                }
                $used = (float) $item->actual_quantity;
                if ($used > 0 && $this->inventory->quantity($material, $kitchen) < $used) {
                    throw ValidationException::withMessages(['items' => 'Stok Central Kitchen tidak cukup untuk '.$material->name.'.']);
                }
                $item->update(['unit_cost' => $material->cost_per_unit]);
                if ($used > 0) {
                    $this->inventory->move($material, $kitchen, -$used, 'adjustment', 'ProductionWorkOrder', $order->id,
                        'Bahan terpakai · surat kerja #'.$order->id, $user->id);
                }
                $totalCost += round($used * (float) $material->cost_per_unit, 6);
            }

            $good = (float) $order->good_quantity;
            $unitCost = $good > 0 ? round($totalCost / $good, 6) : null;
            if ($totalCost >= 1e18 || ($unitCost !== null && $unitCost > 999999999.999999)) {
                throw ValidationException::withMessages(['result' => 'Biaya produksi melebihi batas penyimpanan.']);
            }
            if ($good > 0) {
                $this->inventory->move($output, $kitchen, $good, 'in', 'ProductionWorkOrder', $order->id,
                    'Hasil layak pakai · surat kerja #'.$order->id, $user->id, number_format($unitCost, 6, '.', ''));
            }

            $order->update([
                'status' => ProductionWorkOrder::Posted, 'total_cost' => round($totalCost, 6), 'output_unit_cost' => $unitCost,
                'posted_by' => $user->id, 'posted_at' => now(),
            ]);
            $this->audit($user, 'result_posted', $order, 'Membukukan hasil surat kerja #'.$order->id);
        }, 3);
    }

    private function lockOrder(User $user, int $orderId): ProductionWorkOrder
    {
        return ProductionWorkOrder::where('shop_id', $user->shop_id)->whereKey($orderId)->lockForUpdate()->firstOrFail();
    }

    private function checkRevision(ProductionWorkOrder $order, int $expected): void
    {
        if ($order->result_revision !== $expected) {
            throw ValidationException::withMessages(['result' => 'Hasil sudah berubah. Buka ulang surat kerja sebelum melanjutkan.']);
        }
    }

    private function audit(User $user, string $action, ProductionRecipe|ProductionWorkOrder $entity, string $description): void
    {
        ActivityLog::create([
            'shop_id' => $user->shop_id, 'user_id' => $user->id, 'action' => 'production.'.$action,
            'entity_type' => $entity::class, 'entity_id' => $entity->id, 'description' => $description,
        ]);
    }
}
