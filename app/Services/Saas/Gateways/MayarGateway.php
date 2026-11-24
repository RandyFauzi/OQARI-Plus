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

class MayarGateway implements SaasPaymentGateway
{
    public function name(): string
    {
        return 'mayar';
    }

    /**
     * @return array{payment_url: ?string, snap_token: ?string, external_id: string, raw: array<string, mixed>}
     */
    public function createCheckout(SaasInvoice $invoice, Shop $shop, User $actor): array
    {
        $apiKey = (string) config('saas.gateways.mayar.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('MAYAR_API_KEY belum dikonfigurasi.');
        }

        $baseUrl = config('saas.gateways.mayar.is_production')
            ? 'https://api.mayar.id/hl/v1'
            : 'https://api.mayar.club/hl/v1';

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->post("{$baseUrl}/payment/create", [
                'name' => $actor->name,
                'email' => $actor->email,
                'mobile' => $shop->whatsapp_number ?: '08000000000',
                'amount' => $invoice->amount,
                'description' => sprintf('OQARI SaaS %s (%s) - %s', $invoice->plan?->name ?? 'Plan', $invoice->billing_cycle, $invoice->invoice_number),
                'redirectUrl' => url('/'),
                'extraData' => [
                    'invoice_number' => $invoice->invoice_number,
                    'shop_id' => $shop->id,
                ],
            ])
            ->throw()
            ->json();

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        return [
            'payment_url' => $data['link'] ?? ($response['link'] ?? null),
            'snap_token' => null,
            'external_id' => (string) ($data['id'] ?? $invoice->invoice_number),
            'raw' => is_array($response) ? $response : [],
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $expectedToken = (string) config('saas.gateways.mayar.webhook_token');
        if ($expectedToken === '') {
            return false;
        }

        $providedToken = (string) ($request->header('X-Callback-Token') ?: $request->header('Authorization', ''));
        $providedToken = preg_replace('/^Bearer\s+/i', '', trim($providedToken)) ?? '';

        return $providedToken !== '' && hash_equals($expectedToken, $providedToken);
    }

    /**
     * @return array{invoice_number: string, external_id: ?string, status: string, paid_at: ?CarbonImmutable, raw: array<string, mixed>}
     */
    public function parseWebhook(Request $request): array
    {
        $event = strtolower((string) $request->input('event', ''));
        $data = is_array($request->input('data')) ? $request->input('data') : $request->all();
        $rawStatus = strtolower((string) ($data['status'] ?? ''));

        $status = match (true) {
            in_array($event, ['payment.received', 'payment.paid'], true) || in_array($rawStatus, ['paid', 'settled', 'success'], true) => SaasInvoice::STATUS_PAID,
            in_array($event, ['payment.expired'], true) || $rawStatus === 'expired' => SaasInvoice::STATUS_EXPIRED,
            in_array($event, ['payment.failed'], true) || in_array($rawStatus, ['failed', 'cancelled'], true) => SaasInvoice::STATUS_FAILED,
            default => SaasInvoice::STATUS_PENDING,
        };

        $extraData = is_array($data['extraData'] ?? null) ? $data['extraData'] : [];
        $invoiceNumber = (string) ($extraData['invoice_number'] ?? $request->input('invoice_number', ''));

        return [
            'invoice_number' => $invoiceNumber,
            'external_id' => isset($data['id']) ? (string) $data['id'] : null,
            'status' => $status,
            'paid_at' => $status === SaasInvoice::STATUS_PAID ? CarbonImmutable::now() : null,
            'raw' => $request->all(),
        ];
    }
}
