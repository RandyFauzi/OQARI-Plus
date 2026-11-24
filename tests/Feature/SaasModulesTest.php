<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureActiveSubscription;
use App\Models\SaasInvoice;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\Shop;
use App\Models\User;
use App\Services\Saas\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaasModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_saas_modules_are_dormant_by_default(): void
    {
        $this->assertFalse(config('saas.enabled'));

        $shop = Shop::factory()->create();
        $service = app(SubscriptionService::class);

        $this->assertTrue($service->hasFeature($shop, 'multi_branch'));
        $this->assertTrue($service->withinLimit($shop, 'max_branches', 99));

        $this->postJson('/api/saas/webhooks/midtrans', ['order_id' => 'OQARI-1-TEST'])->assertNotFound();
        $this->postJson('/api/saas/webhooks/mayar', ['event' => 'payment.received'])->assertNotFound();

        $user = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureActiveSubscription::class)->handle($request, fn () => response('ok', 200));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_midtrans_checkout_and_webhook_activate_subscription_idempotently(): void
    {
        config([
            'saas.enabled' => true,
            'saas.gateways.midtrans.server_key' => 'midtrans-secret-key',
            'saas.gateways.midtrans.is_production' => false,
        ]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123',
            ], 201),
        ]);

        $shop = Shop::factory()->create();
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $plan = SaasPlan::create([
            'code' => 'growth',
            'name' => 'Growth',
            'price_monthly' => 199000,
            'price_yearly' => 1990000,
            'max_branches' => 5,
            'max_staff' => 25,
            'features' => ['multi_branch', 'mcp'],
        ]);

        $invoice = app(SubscriptionService::class)->createCheckoutInvoice($shop, $plan, $owner, 'monthly', 'midtrans');

        $this->assertSame('midtrans', $invoice->gateway);
        $this->assertSame('snap-token-123', $invoice->snap_token);
        $this->assertSame(SaasInvoice::STATUS_PENDING, $invoice->status);

        $grossAmount = '199000.00';
        $statusCode = '200';
        $validSignature = hash('sha512', $invoice->invoice_number.$statusCode.$grossAmount.'midtrans-secret-key');

        $this->postJson('/api/saas/webhooks/midtrans', [
            'order_id' => $invoice->invoice_number,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'transaction_id' => 'mt-tx-001',
            'signature_key' => 'invalid-sig',
        ])->assertUnauthorized();

        $this->postJson('/api/saas/webhooks/midtrans', [
            'order_id' => $invoice->invoice_number,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'transaction_id' => 'mt-tx-001',
            'signature_key' => $validSignature,
        ])->assertOk();

        $invoice->refresh();
        $this->assertSame(SaasInvoice::STATUS_PAID, $invoice->status);
        $this->assertSame('mt-tx-001', $invoice->external_id);

        $subscription = SaasSubscription::where('shop_id', $shop->id)->sole();
        $this->assertSame(SaasSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue(app(SubscriptionService::class)->hasFeature($shop, 'mcp'));
        $this->assertTrue(app(SubscriptionService::class)->withinLimit($shop, 'max_branches', 4));
        $this->assertFalse(app(SubscriptionService::class)->withinLimit($shop, 'max_branches', 5));

        $periodEnd = $subscription->current_period_end;

        // Idempotent replay should not extend the period twice
        $this->postJson('/api/saas/webhooks/midtrans', [
            'order_id' => $invoice->invoice_number,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'transaction_id' => 'mt-tx-001',
            'signature_key' => $validSignature,
        ])->assertOk();

        $this->assertTrue($subscription->fresh()->current_period_end->equalTo($periodEnd));
    }

    public function test_mayar_checkout_and_webhook_activate_subscription(): void
    {
        config([
            'saas.enabled' => true,
            'saas.gateways.mayar.api_key' => 'mayar-api-key',
            'saas.gateways.mayar.webhook_token' => 'mayar-webhook-secret',
            'saas.gateways.mayar.is_production' => false,
        ]);

        Http::fake([
            'https://api.mayar.club/hl/v1/payment/create' => Http::response([
                'statusCode' => 200,
                'data' => [
                    'id' => 'mayar-pay-999',
                    'link' => 'https://mayar.club/pay/mayar-pay-999',
                ],
            ], 200),
        ]);

        $shop = Shop::factory()->create();
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $plan = SaasPlan::create([
            'code' => 'starter',
            'name' => 'Starter',
            'price_monthly' => 99000,
            'price_yearly' => 990000,
            'max_branches' => 1,
            'features' => ['pos'],
        ]);

        $invoice = app(SubscriptionService::class)->createCheckoutInvoice($shop, $plan, $owner, 'yearly', 'mayar');

        $this->assertSame('mayar', $invoice->gateway);
        $this->assertSame('https://mayar.club/pay/mayar-pay-999', $invoice->payment_url);
        $this->assertSame('mayar-pay-999', $invoice->external_id);

        $this->postJson('/api/saas/webhooks/mayar', [
            'event' => 'payment.received',
            'data' => [
                'id' => 'mayar-pay-999',
                'status' => 'paid',
                'extraData' => ['invoice_number' => $invoice->invoice_number],
            ],
        ], ['X-Callback-Token' => 'wrong-token'])->assertUnauthorized();

        $this->postJson('/api/saas/webhooks/mayar', [
            'event' => 'payment.received',
            'data' => [
                'id' => 'mayar-pay-999',
                'status' => 'paid',
                'extraData' => ['invoice_number' => $invoice->invoice_number],
            ],
        ], ['X-Callback-Token' => 'mayar-webhook-secret'])->assertOk();

        $this->assertSame(SaasInvoice::STATUS_PAID, $invoice->fresh()->status);
        $subscription = SaasSubscription::where('shop_id', $shop->id)->sole();
        $this->assertSame(SaasSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame('yearly', $subscription->billing_cycle);
    }
}
