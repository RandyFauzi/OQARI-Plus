<?php

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRecipe extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = ['shop_id', 'output_material_id', 'version', 'request_id', 'yield_quantity', 'unit', 'notes', 'created_by'];

    protected $casts = ['version' => 'integer', 'yield_quantity' => 'decimal:6'];

    public function outputMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'output_material_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionRecipeItem::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
