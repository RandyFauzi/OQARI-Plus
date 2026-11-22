<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyPurchase extends Model
{
    use BelongsToBranch, BelongsToShop;

    protected $fillable = [
        'shop_id', 'request_id', 'description', 'notes', 'proof_path', 'proof_name', 'amount', 'funding', 'status',
        'cash_register_session_id', 'cash_drawer_transaction_id', 'raw_material_id', 'quantity', 'unit',
        'decision_note', 'created_by', 'reviewed_by', 'received_by', 'reviewed_at', 'received_at',
    ];

    protected $hidden = ['proof_path'];

    protected $casts = ['amount' => 'decimal:2', 'quantity' => 'decimal:6', 'reviewed_at' => 'datetime', 'received_at' => 'datetime'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'PENDING' => 'Menunggu persetujuan',
            $this->status === 'REJECTED' => 'Ditolak',
            $this->received_at !== null => 'Barang diterima',
            $this->raw_material_id !== null => 'Disetujui, menunggu barang',
            default => 'Disetujui',
        };
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function cashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashDrawerTransaction::class, 'cash_drawer_transaction_id');
    }
}
