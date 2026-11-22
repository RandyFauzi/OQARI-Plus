<?php

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = ['shop_id', 'name', 'contact'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
