<?php

namespace App\Actions\Inventory;

use App\Actions\Register\CalculateExpectedCash;
use App\Models\CashRegisterSession;
use App\Models\InventoryLocation;
use App\Models\PettyPurchase;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManagePettyPurchases
{
    public function __construct(private InventoryService $inventory, private CalculateExpectedCash $cash) {}

    /** @param array{request_id: string, description: string, amount: numeric, funding: string, proof: UploadedFile, notes?: ?string, cash_register_session_id?: ?int, raw_material_id?: ?int, quantity?: ?numeric} $data */
    public function create(User $actor, array $data): PettyPurchase
    {
        $proof = $data['proof'];
        $path = $proof->store('petty-purchases/'.$actor->shop_id, 'local');
        if ($path === false) {
            throw ValidationException::withMessages(['proof' => 'Bukti gagal disimpan. Coba unggah kembali.']);
        }

        try {
            $purchase = DB::transaction(function () use ($actor, $data, $proof, $path): PettyPurchase {
                Shop::whereKey($actor->shop_id)->lockForUpdate()->firstOrFail();
                $existing = PettyPurchase::where('shop_id', $actor->shop_id)->where('request_id', $data['request_id'])->first();
                if ($existing) {
                    abort_unless((int) $existing->created_by === $actor->id, 404);

                    return $existing;
                }
                $session = $data['funding'] === 'DRAWER'
                    ? $this->session($actor, (int) ($data['cash_register_session_id'] ?? 0), own: true)
                    : null;
                $material = ! empty($data['raw_material_id'])
                    ? RawMaterial::where('shop_id', $actor->shop_id)->whereKey($data['raw_material_id'])->firstOrFail()
                    : null;

                return PettyPurchase::create([
                    'shop_id' => $actor->shop_id, 'request_id' => $data['request_id'], 'description' => $data['description'],
                    'amount' => $data['amount'], 'funding' => $data['funding'], 'notes' => $data['notes'] ?? null,
                    'proof_path' => $path, 'proof_name' => mb_substr($proof->getClientOriginalName(), 0, 240),
                    'cash_register_session_id' => $session?->id, 'raw_material_id' => $material?->id,
                    'quantity' => $material ? $data['quantity'] : null, 'unit' => $material?->unit,
                    'created_by' => $actor->id, 'status' => 'PENDING',
                ]);
            }, 3);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);

            throw $error;
        }
        if ($purchase->proof_path !== $path) {
            Storage::disk('local')->delete($path);
        }

        return $purchase;
    }

    public function review(User $actor, int $id, bool $approve, ?string $note): void
    {
        DB::transaction(function () use ($actor, $id, $approve, $note): void {
            $purchase = $this->lock($actor, $id);
            if ($approve && app(BranchContext::class)->id()) {
                if ((int) $purchase->created_by === (int) $actor->id) {
                    throw ValidationException::withMessages(['decision' => 'Pengajuan harus disetujui oleh akun yang berbeda.']);
                }
                if ($actor->role !== 'owner' && (float) $purchase->amount > config('access.branch_purchase_approval_limit')) {
                    throw ValidationException::withMessages(['decision' => 'Nominal ini perlu persetujuan Owner.']);
                }
            }
            $status = $approve ? 'APPROVED' : 'REJECTED';
            if ($purchase->status === $status) {
                return;
            }
            if ($purchase->status !== 'PENDING') {
                throw ValidationException::withMessages(['decision' => 'Pembelian sudah ditinjau.']);
            }
            if ($approve && ! Storage::disk('local')->exists($purchase->proof_path)) {
                throw ValidationException::withMessages(['proof' => 'Bukti pembelian tidak tersedia.']);
            }

            $transaction = null;
            if ($approve && $purchase->funding === 'DRAWER') {
                $session = $this->session($actor, (int) $purchase->cash_register_session_id);
                if ($this->cash->execute($session, true)['expected_cash'] < (float) $purchase->amount) {
                    throw ValidationException::withMessages(['amount' => 'Kas pada shift tersebut tidak mencukupi.']);
                }
                $transaction = $session->transactions()->create([
                    'shop_id' => $actor->shop_id, 'user_id' => $actor->id, 'type' => 'CASH_OUT',
                    'amount' => $purchase->amount, 'description' => 'Pembelian kecil #'.$purchase->id.' · '.$purchase->description,
                ]);
            }
            $purchase->update([
                'status' => $status, 'decision_note' => $note, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'cash_drawer_transaction_id' => $transaction?->id,
            ]);
        }, 3);
    }

    public function receive(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id): void {
            $purchase = $this->lock($actor, $id);
            if ($purchase->received_at !== null) {
                return;
            }
            if ($purchase->status !== 'APPROVED' || $purchase->raw_material_id === null) {
                throw ValidationException::withMessages(['purchase' => 'Konfirmasi penerimaan membutuhkan pembelian bahan yang disetujui.']);
            }
            $outlet = $this->inventory->location($actor->shop_id, InventoryLocation::Outlet);
            $material = RawMaterial::where('shop_id', $actor->shop_id)->whereKey($purchase->raw_material_id)->lockForUpdate()->firstOrFail();
            if (strcasecmp($material->unit, $purchase->unit) !== 0) {
                throw ValidationException::withMessages(['quantity' => 'Satuan bahan sudah berubah sejak diajukan.']);
            }
            $quantity = (float) $purchase->quantity;
            $unitCost = round((float) $purchase->amount / $quantity, 6);
            if ($unitCost > 999999999.999999) {
                throw ValidationException::withMessages(['quantity' => 'Harga per satuan melampaui batas penyimpanan.']);
            }
            $this->inventory->move($material, $outlet, $quantity, 'in', 'PettyPurchase', $purchase->id,
                'Penerimaan pembelian kecil #'.$purchase->id, $actor->id, number_format($unitCost, 6, '.', ''));
            $purchase->update(['received_by' => $actor->id, 'received_at' => now()]);
        }, 3);
    }

    private function session(User $actor, int $id, bool $own = false): CashRegisterSession
    {
        $session = CashRegisterSession::whereKey($id)->where('status', 'OPEN')
            ->whereHas('register', fn ($query) => $query->where('shop_id', $actor->shop_id))
            ->when($own, fn ($query) => $query->where('user_id', $actor->id))->lockForUpdate()->first();
        if (! $session) {
            throw ValidationException::withMessages(['cash_register_session_id' => 'Pilih shift kas yang sesuai dan masih aktif.']);
        }

        return $session;
    }

    private function lock(User $actor, int $id): PettyPurchase
    {
        return PettyPurchase::where('shop_id', $actor->shop_id)->whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
