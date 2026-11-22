<?php

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    use BelongsToShop;

    public const Outlet = 'outlet';

    public const Warehouse = 'warehouse';

    public const Kitchen = 'kitchen';

    protected $fillable = ['shop_id', 'branch_id', 'code', 'name'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }
}
