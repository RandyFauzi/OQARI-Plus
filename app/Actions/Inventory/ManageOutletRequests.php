<?php

namespace App\Actions\Inventory;

use App\Models\InventoryLocation;
use App\Models\OutletStockRequest;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageOutletRequests
{
    public function __construct(private TransferStock $transfers) {}

    /** @param array{request_id: string, raw_material_id: int, source: string, quantity: numeric, notes?: ?string} $data */
    public function create(User $actor, array $data): OutletStockRequest
    {
        return DB::transaction(function () use ($actor, $data): OutletStockRequest {
            Shop::whereKey($actor->shop_id)->lockForUpdate()->firstOrFail();
            $existing = OutletStockRequest::where('shop_id', $actor->shop_id)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                return $existing;
            }
            $material = RawMaterial::where('shop_id', $actor->shop_id)->whereKey($data['raw_material_id'])->firstOrFail();

            return OutletStockRequest::create([
                'shop_id' => $actor->shop_id, 'raw_material_id' => $material->id, 'request_id' => $data['request_id'],
                'source' => $data['source'], 'unit' => $material->unit, 'requested_quantity' => $data['quantity'],
                'notes' => $data['notes'] ?? null, 'status' => 'PENDING', 'created_by' => $actor->id,
            ]);
        }, 3);
    }

    public function review(User $actor, int $id, bool $approve, ?float $quantity, ?string $note): void
    {
        DB::transaction(function () use ($actor, $id, $approve, $quantity, $note): void {
            $request = $this->lock($actor, $id);
            $status = $approve ? 'APPROVED' : 'REJECTED';
            if ($request->status === $status) {
                return;
            }
            if ($request->status !== 'PENDING') {
                throw ValidationException::withMessages(['decision' => 'Permintaan sudah ditinjau.']);
            }
            if ($approve && ($quantity === null || $quantity <= 0 || $quantity > (float) $request->requested_quantity)) {
                throw ValidationException::withMessages(['approved_quantity' => 'Jumlah disetujui harus positif dan tidak melebihi permintaan.']);
            }
            $request->update([
                'status' => $status, 'approved_quantity' => $approve ? $quantity : null,
                'decision_note' => $note, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
            ]);
        }, 3);
    }

    public function dispatch(User $actor, int $id, string $requestId): void
    {
        DB::transaction(function () use ($actor, $id, $requestId): void {
            Shop::whereKey($actor->shop_id)->lockForUpdate()->firstOrFail();
            $request = $this->lock($actor, $id);
            if ($request->stock_transfer_id !== null) {
                return;
            }
            if ($request->status !== 'APPROVED') {
                throw ValidationException::withMessages(['decision' => 'Setujui permintaan sebelum mengirim barang.']);
            }
            $material = RawMaterial::where('shop_id', $actor->shop_id)->whereKey($request->raw_material_id)->lockForUpdate()->firstOrFail();
            if (strcasecmp($material->unit, $request->unit) !== 0) {
                throw ValidationException::withMessages(['quantity' => 'Satuan bahan sudah berubah. Buat permintaan baru.']);
            }
            $route = $request->source === InventoryLocation::Kitchen ? 'kitchen-outlet' : TransferStock::DefaultRoute;
            $transfer = $this->transfers->send($actor, $request->raw_material_id, (float) $request->approved_quantity, $requestId, $route);
            if (! $transfer->wasRecentlyCreated) {
                throw ValidationException::withMessages(['request_id' => 'Kunci transfer sudah terpakai. Muat ulang halaman.']);
            }
            $request->update(['stock_transfer_id' => $transfer->id]);
        }, 3);
    }

    private function lock(User $actor, int $id): OutletStockRequest
    {
        return OutletStockRequest::where('shop_id', $actor->shop_id)->whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
