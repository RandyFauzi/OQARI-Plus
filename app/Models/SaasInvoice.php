<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasInvoice extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'invoice_number',
        'shop_id',
        'saas_subscription_id',
        'saas_plan_id',
        'billing_cycle',
        'gateway',
        'external_id',
        'amount',
        'currency',
        'status',
        'payment_url',
        'snap_token',
        'paid_at',
        'expires_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'raw_payload' => 'array',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SaasSubscription::class, 'saas_subscription_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }
}
