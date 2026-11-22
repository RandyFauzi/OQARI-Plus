<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $fillable = ['raw_material_id', 'last_mutation_id', 'unit', 'system_quantity', 'counted_quantity', 'difference'];

    protected $casts = ['last_mutation_id' => 'integer', 'system_quantity' => 'decimal:6', 'counted_quantity' => 'decimal:6', 'difference' => 'decimal:6'];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }
}
