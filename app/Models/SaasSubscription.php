<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasSubscription extends Model
{
    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'shop_id',
        'saas_plan_id',
        'billing_cycle',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'meta' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class);
    }

    public function isAccessible(): bool
    {
        $now = now();

        if ($this->status === self::STATUS_TRIALING) {
            return $this->trial_ends_at === null || $this->trial_ends_at->isFuture();
        }

        if ($this->status === self::STATUS_ACTIVE) {
            return $this->current_period_end === null || $this->current_period_end->isFuture();
        }

        if ($this->status === self::STATUS_PAST_DUE) {
            $graceDays = (int) config('saas.grace_days', 3);

            return $this->current_period_end !== null
                && $this->current_period_end->addDays($graceDays)->greaterThan($now);
        }

        return false;
    }
}
