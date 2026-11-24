<?php

namespace App\Contracts;

use App\Models\SaasInvoice;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

interface SaasPaymentGateway
{
    public function name(): string;

    /**
     * @return array{payment_url: ?string, snap_token: ?string, external_id: string, raw: array<string, mixed>}
     */
    public function createCheckout(SaasInvoice $invoice, Shop $shop, User $actor): array;

    public function verifyWebhookSignature(Request $request): bool;

    /**
     * @return array{invoice_number: string, external_id: ?string, status: string, paid_at: ?CarbonImmutable, raw: array<string, mixed>}
     */
    public function parseWebhook(Request $request): array;
}
