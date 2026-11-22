<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['raw_material_id', 'unit', 'quantity', 'unit_cost', 'accepted_quantity'];

    protected $casts = ['quantity' => 'decimal:6', 'unit_cost' => 'decimal:6', 'accepted_quantity' => 'decimal:6'];

    public function remainingQuantity(): float
    {
        return round((float) $this->quantity - (float) $this->accepted_quantity, 6);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
