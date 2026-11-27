<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopDomain extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'shop_id',
        'domain',
        'verification_token',
        'status',
        'cloudflare_hostname_id',
        'ssl_status',
        'is_primary',
        'last_error',
        'verified_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function txtRecordName(): string
    {
        return config('custom_domains.txt_prefix', '_oqari-verify').'.'.$this->domain;
    }

    public function txtRecordValue(): string
    {
        return 'oqari-verify='.$this->verification_token;
    }

    public function cnameTarget(): string
    {
        return (string) config('custom_domains.cname_target', 'cname.oqariplus.appku.site');
    }
}
