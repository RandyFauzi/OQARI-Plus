<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Modifier extends Model
{
    use HasFactory;

    protected $fillable = [
        'modifier_group_id',
        'name',
        'price_adjustment',
        'cogs_adjustment',
        'is_active',
    ];

    protected $casts = [
        'price_adjustment' => 'float',
        'cogs_adjustment' => 'float',
        'is_active' => 'boolean',
    ];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(RawMaterial::class, 'modifier_recipes')->withPivot('amount')->withTrashed();
    }

    public function scopeAvailableFor(Builder $query, Product $product): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('group', fn (Builder $group) => $group->where('is_active', true)
                ->where(function (Builder $linked) use ($product): void {
                    $linked->whereHas('products', fn (Builder $products) => $products->where('products.id', $product->id))
                        ->orWhere('modifier_groups.product_id', $product->id);
                }));
    }

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
