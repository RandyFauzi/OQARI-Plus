<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecipeItem extends Model
{
    protected $fillable = ['raw_material_id', 'quantity', 'unit'];

    protected $casts = ['quantity' => 'decimal:6'];

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }
}
