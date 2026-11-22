<?php

namespace App\Actions\Inventory;

use App\Models\InventoryLocation;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferStock
{
    public const DefaultRoute = 'warehouse-outlet';

    public const Routes = [
        self::DefaultRoute => [InventoryLocation::Warehouse, InventoryLocation::Outlet],
        'warehouse-kitchen' => [InventoryLocation::Warehouse, InventoryLocation::Kitchen],
        'kitchen-warehouse' => [InventoryLocation::Kitchen, InventoryLocation::Warehouse],
        'kitchen-outlet' => [InventoryLocation::Kitchen, InventoryLocation::Outlet],
    ];

    public function __construct(private InventoryService $inventory) {}

    public function setWarehouseStock(User $user, int $materialId, float $quantity, string $notes): void
    {
        DB::transaction(function () use ($user, $materialId, $quantity, $notes): void {
            $warehouse = $this->inventory->location($user->shop_id, InventoryLocation::Warehouse);
            $material = RawMaterial::where('shop_id', $user->shop_id)->whereKey($materialId)->lockForUpdate()->firstOrFail();
            $delta = round($quantity - $this->inventory->quantity($material, $warehouse), 6);
            if ($delta !== 0.0) {
                $this->inventory->move($material, $warehouse, $delta, 'adjustment', 'WarehouseCount', null, $notes, $user->id);
            }
        }, 3);
    }

    public function send(User $user, int $materialId, float $quantity, string $requestId, string $route = self::DefaultRoute): StockTransfer
    {
        if (! isset(self::Routes[$route])) {
            throw ValidationException::withMessages(['route' => 'Jalur transfer tidak tersedia.']);
        }

        return DB::transaction(function () use ($user, $materialId, $quantity, $requestId, $route): StockTransfer {
            $shopId = Shop::whereKey($user->shop_id)->lockForUpdate()->firstOrFail()->id;
            [$sourceCode, $destinationCode] = self::Routes[$route];
            $source = $this->inventory->location($shopId, $sourceCode);
            $destination = $this->inventory->location($shopId, $destinationCode);
            $existing = StockTransfer::where('shop_id', $shopId)->where('request_id', $requestId)->first();
            if ($existing) {
                if ((int) $existing->raw_material_id !== $materialId || (float) $existing->sent_quantity !== $quantity
                    || (int) $existing->from_location_id !== $source->id || (int) $existing->to_location_id !== $destination->id) {
                    throw ValidationException::withMessages(['request_id' => 'Permintaan kirim sudah digunakan untuk transfer lain.']);
                }

                return $existing;
            }

            $material = RawMaterial::where('shop_id', $shopId)->whereKey($materialId)->lockForUpdate()->firstOrFail();
            if ($this->inventory->quantity($material, $source) < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah kirim melebihi stok '.$source->name.'.']);
            }
            $transfer = StockTransfer::create([
                'shop_id' => $shopId, 'request_id' => $requestId, 'raw_material_id' => $material->id,
                'from_location_id' => $source->id, 'to_location_id' => $destination->id,
                'sent_quantity' => $quantity, 'sent_by' => $user->id,
            ]);
            $this->inventory->move($material, $source, -$quantity, 'adjustment', 'StockTransfer', $transfer->id, 'Dikirim ke '.$destination->name, $user->id);

            return $transfer->refresh();
        }, 3);
    }

    public function receive(User $user, int $transferId, float $totalReceived): StockTransfer
    {
        return DB::transaction(function () use ($user, $transferId, $totalReceived): StockTransfer {
            $transfer = StockTransfer::where('shop_id', $user->shop_id)->whereKey($transferId)->lockForUpdate()->firstOrFail();
            if ($branchId = app(BranchContext::class)->id((int) $user->shop_id)) {
                if ($user->role !== 'owner' || $transfer->toLocation->branch_id !== null) {
                    abort_unless((int) $transfer->toLocation->branch_id === $branchId, 404);
                }
            }
            if ($totalReceived > (float) $transfer->sent_quantity) {
                throw ValidationException::withMessages(['quantity' => 'Total penerimaan tidak boleh melebihi jumlah kirim.']);
            }
            $delta = round($totalReceived - (float) $transfer->received_quantity, 6);
            if ($delta <= 0) {
                return $transfer;
            }

            $material = RawMaterial::withTrashed()->where('shop_id', $user->shop_id)
                ->whereKey($transfer->raw_material_id)->lockForUpdate()->firstOrFail();
            $this->inventory->move($material, $transfer->toLocation, $delta, 'in', 'StockTransfer', $transfer->id, 'Diterima dari '.$transfer->fromLocation->name, $user->id);
            $transfer->update(['received_quantity' => $totalReceived, 'received_by' => $user->id, 'received_at' => now()]);

            return $transfer;
        }, 3);
    }
}
