<?php

namespace App\Http\Middleware;

use App\Services\Saas\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('saas.enabled')) {
            return $next($request);
        }

        $shop = $request->user()?->shop;
        if (! $shop) {
            return $next($request);
        }

        $subscription = $this->subscriptions->activeSubscription($shop);
        if ($subscription && $subscription->isAccessible()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Langganan SaaS Anda belum aktif atau sudah berakhir.'], 402);
        }

        abort(402, 'Langganan SaaS Anda belum aktif atau sudah berakhir.');
    }
}
