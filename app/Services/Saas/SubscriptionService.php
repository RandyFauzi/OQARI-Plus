<?php

namespace App\Services\Saas;

use App\Models\SaasInvoice;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function activeSubscription(Shop $shop): ?SaasSubscription
    {
        return SaasSubscription::query()
            ->with('plan')
            ->where('shop_id', $shop->id)
            ->whereIn('status', [
                SaasSubscription::STATUS_ACTIVE,
                SaasSubscription::STATUS_TRIALING,
                SaasSubscription::STATUS_PAST_DUE,
            ])
            ->latest('id')
            ->first();
    }

    public function startTrial(Shop $shop, SaasPlan $plan, ?int $trialDays = null): SaasSubscription
    {
        $days = $trialDays ?? (int) config('saas.trial_days', 14);
        $now = CarbonImmutable::now();

        return SaasSubscription::create([
            'shop_id' => $shop->id,
            'saas_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => SaasSubscription::STATUS_TRIALING,
            'trial_ends_at' => $now->addDays($days),
            'current_period_start' => $now,
            'current_period_end' => $now->addDays($days),
        ]);
    }

    public function createCheckoutInvoice(
        Shop $shop,
        SaasPlan $plan,
        User $actor,
        string $billingCycle = 'monthly',
        ?string $gatewayName = null,
    ): SaasInvoice {
        $gateway = $this->gateways->driver($gatewayName);
        $amount = $plan->priceForCycle($billingCycle);
        $invoiceNumber = sprintf('OQARI-%d-%s', $shop->id, Str::upper(Str::random(10)));

        $invoice = SaasInvoice::create([
            'invoice_number' => $invoiceNumber,
            'shop_id' => $shop->id,
            'saas_subscription_id' => $this->activeSubscription($shop)?->id,
            'saas_plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'gateway' => $gateway->name(),
            'amount' => $amount,
            'currency' => (string) config('saas.currency', 'IDR'),
            'status' => SaasInvoice::STATUS_PENDING,
            'expires_at' => CarbonImmutable::now()->addHours(24),
        ]);

        $checkout = $gateway->createCheckout($invoice->loadMissing('plan'), $shop, $actor);

        $invoice->update([
            'payment_url' => $checkout['payment_url'],
            'snap_token' => $checkout['snap_token'],
            'external_id' => $checkout['external_id'],
            'raw_payload' => $checkout['raw'],
        ]);

        return $invoice->fresh();
    }

    public function activateFromInvoice(SaasInvoice $invoice, ?CarbonImmutable $paidAt = null): SaasSubscription
    {
        return DB::transaction(function () use ($invoice, $paidAt): SaasSubscription {
            $locked = SaasInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $now = $paidAt ?? CarbonImmutable::now();

            if ($locked->status !== SaasInvoice::STATUS_PAID) {
                $locked->update([
                    'status' => SaasInvoice::STATUS_PAID,
                    'paid_at' => $now,
                ]);
            }

            $existing = $locked->saas_subscription_id
                ? SaasSubscription::query()->whereKey($locked->saas_subscription_id)->lockForUpdate()->first()
                : null;

            if ($existing && $locked->wasChanged('status') === false && $existing->status === SaasSubscription::STATUS_ACTIVE) {
                return $existing;
            }

            $periodStart = ($existing?->current_period_end && $existing->current_period_end->isFuture())
                ? $existing->current_period_end
                : $now;

            $periodEnd = $locked->billing_cycle === 'yearly'
                ? $periodStart->addYear()
                : $periodStart->addMonth();

            if ($existing) {
                $existing->update([
                    'saas_plan_id' => $locked->saas_plan_id,
                    'billing_cycle' => $locked->billing_cycle,
                    'status' => SaasSubscription::STATUS_ACTIVE,
                    'current_period_start' => $existing->current_period_start ?? $now,
                    'current_period_end' => $periodEnd,
                    'cancelled_at' => null,
                ]);

                return $existing->fresh();
            }

            $subscription = SaasSubscription::create([
                'shop_id' => $locked->shop_id,
                'saas_plan_id' => $locked->saas_plan_id,
                'billing_cycle' => $locked->billing_cycle,
                'status' => SaasSubscription::STATUS_ACTIVE,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
            ]);

            $locked->update(['saas_subscription_id' => $subscription->id]);

            return $subscription;
        });
    }

    public function hasFeature(Shop $shop, string $feature): bool
    {
        if (! config('saas.enabled')) {
            return true;
        }

        $subscription = $this->activeSubscription($shop);
        if (! $subscription || ! $subscription->isAccessible()) {
            return false;
        }

        return $subscription->plan?->hasFeature($feature) ?? false;
    }

    public function withinLimit(Shop $shop, string $limitColumn, int $currentCount): bool
    {
        if (! config('saas.enabled')) {
            return true;
        }

        $subscription = $this->activeSubscription($shop);
        if (! $subscription || ! $subscription->isAccessible() || ! $subscription->plan) {
            return false;
        }

        $limit = $subscription->plan->{$limitColumn} ?? null;

        return $limit === null || $currentCount < (int) $limit;
    }
}
