<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    protected $fillable = ['inventory_location_id', 'raw_material_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:6'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
