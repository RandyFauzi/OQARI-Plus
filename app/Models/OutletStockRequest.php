<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletStockRequest extends Model
{
    use BelongsToBranch, BelongsToShop, HasFactory;

    protected $fillable = ['shop_id', 'raw_material_id', 'stock_transfer_id', 'request_id', 'source', 'unit', 'requested_quantity', 'approved_quantity', 'status', 'notes', 'decision_note', 'created_by', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['requested_quantity' => 'decimal:6', 'approved_quantity' => 'decimal:6', 'reviewed_at' => 'datetime'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function sourceName(): string
    {
        return $this->source === InventoryLocation::Kitchen ? 'Central Kitchen' : 'Gudang Pusat';
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'PENDING' => 'Menunggu persetujuan',
            $this->status === 'REJECTED' => 'Ditolak',
            $this->transfer === null => 'Disetujui, menunggu kirim',
            $this->transfer->remainingQuantity() === 0.0 => 'Diterima lengkap',
            (float) $this->transfer->received_quantity > 0 => 'Diterima sebagian',
            default => 'Dalam perjalanan',
        };
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }
}
