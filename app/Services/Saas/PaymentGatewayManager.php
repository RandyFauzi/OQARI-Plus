<?php

namespace App\Services\Saas;

use App\Contracts\SaasPaymentGateway;
use App\Services\Saas\Gateways\MayarGateway;
use App\Services\Saas\Gateways\MidtransGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function __construct(
        private MidtransGateway $midtrans,
        private MayarGateway $mayar,
    ) {}

    public function driver(?string $gateway = null): SaasPaymentGateway
    {
        $resolved = $gateway ?: (string) config('saas.default_gateway', 'midtrans');

        return match ($resolved) {
            'midtrans' => $this->midtrans,
            'mayar' => $this->mayar,
            default => throw new InvalidArgumentException("Gateway pembayaran SaaS tidak dikenal: {$resolved}"),
        };
    }
}
