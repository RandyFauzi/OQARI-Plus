<?php

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionWorkOrder extends Model
{
    use BelongsToShop;

    public const Planned = 'PLANNED';

    public const Review = 'REVIEW';

    public const Posted = 'POSTED';

    protected $fillable = [
        'shop_id', 'production_recipe_id', 'inventory_location_id', 'request_id', 'status', 'target_quantity',
        'good_quantity', 'waste_quantity', 'waste_reason', 'notes', 'result_revision', 'total_cost',
        'output_unit_cost', 'created_by', 'recorded_by', 'posted_by', 'recorded_at', 'posted_at',
    ];

    protected $casts = [
        'target_quantity' => 'decimal:6', 'good_quantity' => 'decimal:6', 'waste_quantity' => 'decimal:6',
        'total_cost' => 'decimal:6', 'output_unit_cost' => 'decimal:6', 'result_revision' => 'integer',
        'recorded_at' => 'datetime', 'posted_at' => 'datetime',
    ];

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::Planned => 'Direncanakan',
            self::Review => 'Menunggu validasi',
            self::Posted => 'Dibukukan',
        };
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionWorkOrderItem::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
