<?php

namespace App\Services\Saas\Gateways;

use App\Contracts\SaasPaymentGateway;
use App\Models\SaasInvoice;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransGateway implements SaasPaymentGateway
{
    public function name(): string
    {
        return 'midtrans';
    }

    /**
     * @return array{payment_url: ?string, snap_token: ?string, external_id: string, raw: array<string, mixed>}
     */
    public function createCheckout(SaasInvoice $invoice, Shop $shop, User $actor): array
    {
        $serverKey = (string) config('saas.gateways.midtrans.server_key');
        if ($serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }

        $baseUrl = config('saas.gateways.midtrans.is_production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';

        $response = Http::withBasicAuth($serverKey, '')
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->post("{$baseUrl}/snap/v1/transactions", [
                'transaction_details' => [
                    'order_id' => $invoice->invoice_number,
                    'gross_amount' => $invoice->amount,
                ],
                'customer_details' => [
                    'first_name' => $actor->name,
                    'email' => $actor->email,
                    'phone' => $shop->whatsapp_number,
                ],
                'item_details' => [
                    [
                        'id' => (string) $invoice->saas_plan_id,
                        'price' => $invoice->amount,
                        'quantity' => 1,
                        'name' => sprintf('Langganan %s (%s)', $invoice->plan?->name ?? 'OQARI SaaS', $invoice->billing_cycle),
                    ],
                ],
            ])
            ->throw()
            ->json();

        return [
            'payment_url' => $response['redirect_url'] ?? null,
            'snap_token' => $response['token'] ?? null,
            'external_id' => $invoice->invoice_number,
            'raw' => is_array($response) ? $response : [],
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $serverKey = (string) config('saas.gateways.midtrans.server_key');
        if ($serverKey === '') {
            return false;
        }

        $orderId = (string) $request->input('order_id', '');
        $statusCode = (string) $request->input('status_code', '');
        $grossAmount = (string) $request->input('gross_amount', '');
        $signature = (string) $request->input('signature_key', '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signature === '') {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signature);
    }

    /**
     * @return array{invoice_number: string, external_id: ?string, status: string, paid_at: ?CarbonImmutable, raw: array<string, mixed>}
     */
    public function parseWebhook(Request $request): array
    {
        $transactionStatus = strtolower((string) $request->input('transaction_status', ''));
        $fraudStatus = strtolower((string) $request->input('fraud_status', 'accept'));

        $status = match ($transactionStatus) {
            'settlement' => SaasInvoice::STATUS_PAID,
            'capture' => $fraudStatus === 'accept' ? SaasInvoice::STATUS_PAID : SaasInvoice::STATUS_FAILED,
            'expire' => SaasInvoice::STATUS_EXPIRED,
            'cancel', 'deny' => SaasInvoice::STATUS_FAILED,
            'refund', 'partial_refund' => SaasInvoice::STATUS_REFUNDED,
            default => SaasInvoice::STATUS_PENDING,
        };

        $settlementTime = $request->input('settlement_time') ?: $request->input('transaction_time');

        return [
            'invoice_number' => (string) $request->input('order_id', ''),
            'external_id' => $request->input('transaction_id') ? (string) $request->input('transaction_id') : null,
            'status' => $status,
            'paid_at' => $status === SaasInvoice::STATUS_PAID && $settlementTime
                ? CarbonImmutable::parse((string) $settlementTime)
                : ($status === SaasInvoice::STATUS_PAID ? CarbonImmutable::now() : null),
            'raw' => $request->all(),
        ];
    }
}
