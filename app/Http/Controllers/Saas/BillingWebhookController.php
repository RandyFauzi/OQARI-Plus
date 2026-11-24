<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\SaasInvoice;
use App\Services\Saas\PaymentGatewayManager;
use App\Services\Saas\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingWebhookController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private SubscriptionService $subscriptions,
    ) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        abort_unless(config('saas.enabled'), 404);

        try {
            $driver = $this->gateways->driver($gateway);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        if (! $driver->verifyWebhookSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $driver->parseWebhook($request);
        if ($payload['invoice_number'] === '') {
            return response()->json(['message' => 'Missing invoice reference.'], 422);
        }

        $invoice = DB::transaction(function () use ($payload): ?SaasInvoice {
            $record = SaasInvoice::query()
                ->where('invoice_number', $payload['invoice_number'])
                ->lockForUpdate()
                ->first();

            if (! $record) {
                return null;
            }

            if ($record->status === SaasInvoice::STATUS_PAID) {
                return $record;
            }

            $record->update([
                'external_id' => $payload['external_id'] ?? $record->external_id,
                'status' => $payload['status'],
                'raw_payload' => $payload['raw'],
            ]);

            return $record;
        });

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        if ($payload['status'] === SaasInvoice::STATUS_PAID) {
            $this->subscriptions->activateFromInvoice($invoice, $payload['paid_at']);
        }

        return response()->json(['status' => 'ok']);
    }
}
