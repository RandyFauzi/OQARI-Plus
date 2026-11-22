<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionWorkOrderItem extends Model
{
    protected $fillable = ['raw_material_id', 'unit', 'planned_quantity', 'actual_quantity', 'unit_cost'];

    protected $casts = ['planned_quantity' => 'decimal:6', 'actual_quantity' => 'decimal:6', 'unit_cost' => 'decimal:6'];

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionWorkOrder::class, 'production_work_order_id');
    }
}
