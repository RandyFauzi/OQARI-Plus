<?php

namespace App\Http\Middleware;

use App\Services\CustomDomain\CustomDomainService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCustomDomain
{
    public function __construct(private CustomDomainService $domains) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('custom_domains.enabled')) {
            return $next($request);
        }

        $host = $request->getHost();
        if ($this->domains->isSystemHost($host)) {
            return $next($request);
        }

        if ($request->is('superadmin', 'superadmin/*')) {
            abort(404);
        }

        $shop = $this->domains->resolveActiveShopByHost($host);
        if (! $shop) {
            abort(404);
        }

        $request->attributes->set('custom_domain_shop', $shop);

        return $next($request);
    }
}
