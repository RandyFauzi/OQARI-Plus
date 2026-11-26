<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['shop_id', 'name', 'slug', 'address', 'is_primary', 'is_active', 'settings'];

    protected $hidden = ['static_qris_path'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function hasCustomSettings(): bool
    {
        return is_array($this->settings) && count($this->settings) > 0;
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BranchMembership::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
