<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'product_id',
        'is_available',
        'is_sold_out',
        'price_override',
        'discount_price_override',
        'is_discount_active_override',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'is_sold_out' => 'boolean',
            'price_override' => 'decimal:2',
            'discount_price_override' => 'decimal:2',
            'is_discount_active_override' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
