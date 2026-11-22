<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftTemplate extends Model
{
    use BelongsToBranch, BelongsToShop, HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'start_time',
        'end_time',
        'color',
        'is_active',
    ];
}
