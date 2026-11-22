<?php

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    use BelongsToShop;

    protected $fillable = [
        'shop_id', 'request_id', 'from_location_id', 'to_location_id', 'raw_material_id',
        'sent_quantity', 'received_quantity', 'sent_by', 'received_by', 'received_at',
    ];

    protected $casts = ['sent_quantity' => 'decimal:6', 'received_quantity' => 'decimal:6', 'received_at' => 'datetime'];

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function remainingQuantity(): float
    {
        return round((float) $this->sent_quantity - (float) $this->received_quantity, 6);
    }
}
