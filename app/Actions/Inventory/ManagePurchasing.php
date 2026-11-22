<?php

namespace App\Actions\Inventory;

use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\InventoryLocation;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagePurchasing
{
    public function __construct(private InventoryService $inventory) {}

    public function addSupplier(User $actor, string $name, ?string $contact): Supplier
    {
        return DB::transaction(function () use ($actor, $name, $contact): Supplier {
            $supplier = Supplier::firstOrCreate(['shop_id' => $actor->shop_id, 'name' => $name], ['contact' => $contact]);
            if ($supplier->wasRecentlyCreated) {
                $this->log($actor, 'purchasing.supplier_created', Supplier::class, $supplier->id, 'Menambahkan supplier '.$supplier->name);
            }

            return $supplier;
        }, 3);
    }

    /**
     * @param  array{supplier_id: int, request_id: string, notes?: ?string, items: array<array{raw_material_id: int, quantity: numeric, unit_cost: numeric}>}  $data
     */
    public function createOrder(User $actor, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $data): PurchaseOrder {
            $shopId = Shop::whereKey($actor->shop_id)->lockForUpdate()->firstOrFail()->id;
            $items = collect($data['items'])->map(fn (array $item) => [
                'raw_material_id' => (int) $item['raw_material_id'],
                'quantity' => (float) $item['quantity'],
                'unit_cost' => (float) $item['unit_cost'],
            ])->sortBy('raw_material_id')->values();
            $hash = hash('sha256', json_encode([(int) $data['supplier_id'], $data['notes'] ?? null, $items], JSON_THROW_ON_ERROR));
            $existing = PurchaseOrder::where('shop_id', $shopId)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                $this->checkReplay($existing->request_hash, $hash);

                return $existing;
            }

            $supplier = Supplier::where('shop_id', $shopId)->whereKey($data['supplier_id'])->firstOrFail();
            $materials = RawMaterial::where('shop_id', $shopId)->whereIn('id', $items->pluck('raw_material_id'))
                ->get()->keyBy('id');
            abort_unless($materials->count() === $items->count(), 404);

            $order = PurchaseOrder::create([
                'shop_id' => $shopId, 'supplier_id' => $supplier->id, 'request_id' => $data['request_id'],
                'request_hash' => $hash, 'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id, 'status' => PurchaseOrder::Draft,
            ]);
            foreach ($items as $item) {
                $order->items()->create($item + ['unit' => $materials[$item['raw_material_id']]->unit]);
            }
            $this->log($actor, 'purchasing.order_created', PurchaseOrder::class, $order->id, 'Membuat PO #'.$order->id);

            return $order;
        }, 3);
    }

    public function approve(User $actor, int $orderId): void
    {
        DB::transaction(function () use ($actor, $orderId): void {
            $order = $this->lockOrder($actor, $orderId);
            if ($order->status === PurchaseOrder::Draft) {
                $order->update(['status' => PurchaseOrder::Approved, 'approved_by' => $actor->id, 'approved_at' => now()]);
                $this->log($actor, 'purchasing.order_approved', PurchaseOrder::class, $order->id, 'Menyetujui PO #'.$order->id);
            }
        }, 3);
    }

    /**
     * @param  array{request_id: string, delivery_reference: string, notes?: ?string, items: array<array{purchase_order_item_id: int, accepted_quantity: numeric, rejected_quantity: numeric, rejection_reason?: ?string}>}  $data
     */
    public function receive(User $actor, int $orderId, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($actor, $orderId, $data): GoodsReceipt {
            $order = $this->lockOrder($actor, $orderId);
            $lines = collect($data['items'])->map(fn (array $item) => [
                'purchase_order_item_id' => (int) $item['purchase_order_item_id'],
                'accepted_quantity' => (float) $item['accepted_quantity'],
                'rejected_quantity' => (float) $item['rejected_quantity'],
                'rejection_reason' => $item['rejection_reason'] ?? null,
            ])->filter(fn (array $item) => $item['accepted_quantity'] > 0 || $item['rejected_quantity'] > 0)
                ->sortBy('purchase_order_item_id')->keyBy('purchase_order_item_id');
            $reference = mb_strtoupper($data['delivery_reference']);
            $hash = hash('sha256', json_encode([$reference, $data['notes'] ?? null, $lines->values()], JSON_THROW_ON_ERROR));

            $existing = $order->receipts()->where('request_id', $data['request_id'])->first();
            if ($existing) {
                $this->checkReplay($existing->request_hash, $hash);

                return $existing;
            }
            if ($order->receipts()->where('delivery_reference', $reference)->exists()) {
                throw ValidationException::withMessages(['delivery_reference' => 'Surat jalan ini sudah dicatat untuk PO tersebut.']);
            }
            if (! in_array($order->status, [PurchaseOrder::Approved, PurchaseOrder::Partial], true)) {
                throw ValidationException::withMessages(['purchase_order' => 'Penerimaan hanya dapat dicatat untuk PO yang disetujui dan masih terbuka.']);
            }
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Isi jumlah diterima atau ditolak untuk minimal satu bahan.']);
            }

            $ordered = $order->items()->orderBy('raw_material_id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($lines->keys()->diff($ordered->keys())->isEmpty(), 404);
            $warehouse = $this->inventory->location($actor->shop_id, InventoryLocation::Warehouse);
            $receivedItems = $ordered->only($lines->keys()->all());
            $materialIds = $receivedItems->pluck('raw_material_id');
            $materials = RawMaterial::where('shop_id', $actor->shop_id)->whereIn('id', $materialIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $receipt = $order->receipts()->create([
                'inventory_location_id' => $warehouse->id, 'request_id' => $data['request_id'],
                'request_hash' => $hash, 'delivery_reference' => $reference,
                'notes' => $data['notes'] ?? null, 'received_by' => $actor->id,
            ]);

            foreach ($receivedItems as $item) {
                $line = $lines[$item->id];
                $accepted = $line['accepted_quantity'];
                $rejected = $line['rejected_quantity'];
                $material = $materials->get($item->raw_material_id);
                if (round($accepted + $rejected, 6) > $item->remainingQuantity()) {
                    throw ValidationException::withMessages(['items' => 'Jumlah datang melebihi sisa PO untuk '.$item->rawMaterial->name.'.']);
                }
                if ($rejected > 0 && blank($line['rejection_reason'])) {
                    throw ValidationException::withMessages(['items' => 'Isi alasan penolakan untuk '.$item->rawMaterial->name.'.']);
                }
                if ($accepted > 0) {
                    if (! $material || strtolower($material->unit) !== strtolower($item->unit)) {
                        throw ValidationException::withMessages(['items' => 'Bahan diarsipkan atau satuannya berubah sejak PO dibuat.']);
                    }
                    $this->inventory->move($material, $warehouse, $accepted, 'in', 'GoodsReceipt', $receipt->id,
                        'Penerimaan PO #'.$order->id.' · '.$reference, $actor->id, $item->unit_cost);
                    $item->update(['accepted_quantity' => round((float) $item->accepted_quantity + $accepted, 6)]);
                }
                $receipt->items()->create($line);
            }

            $status = match (true) {
                $ordered->every(fn ($item) => $item->remainingQuantity() === 0.0) => PurchaseOrder::Received,
                $ordered->contains(fn ($item) => (float) $item->accepted_quantity > 0) => PurchaseOrder::Partial,
                default => PurchaseOrder::Approved,
            };
            $order->update(['status' => $status]);
            $this->log($actor, 'purchasing.goods_received', GoodsReceipt::class, $receipt->id, 'Mencatat penerimaan PO #'.$order->id.' · '.$reference);

            return $receipt;
        }, 3);
    }

    private function lockOrder(User $actor, int $orderId): PurchaseOrder
    {
        return PurchaseOrder::where('shop_id', $actor->shop_id)->whereKey($orderId)->lockForUpdate()->firstOrFail();
    }

    private function checkReplay(string $storedHash, string $hash): void
    {
        if (! hash_equals($storedHash, $hash)) {
            throw ValidationException::withMessages(['request_id' => 'Permintaan ini sudah dipakai untuk data berbeda.']);
        }
    }

    private function log(User $actor, string $action, string $entityType, int $id, string $description): void
    {
        ActivityLog::create([
            'shop_id' => $actor->shop_id, 'user_id' => $actor->id, 'action' => $action,
            'entity_type' => $entityType, 'entity_id' => $id, 'description' => $description,
        ]);
    }
}
