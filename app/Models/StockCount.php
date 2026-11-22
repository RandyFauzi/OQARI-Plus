<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    use BelongsToBranch, BelongsToShop;

    protected $fillable = ['shop_id', 'inventory_location_id', 'request_id', 'frequency', 'counted_on', 'period_start', 'period_end', 'status', 'notes', 'decision_note', 'created_by', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['counted_on' => 'date', 'period_start' => 'date', 'period_end' => 'date', 'reviewed_at' => 'datetime'];

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
        return match ($this->status) {
            'PENDING' => 'Menunggu persetujuan',
            'REJECTED' => 'Ditolak',
            'POSTED' => 'Dibukukan',
        };
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }
}
