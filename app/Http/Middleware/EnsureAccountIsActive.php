<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $expired = $user && $request->hasSession() && (int) $request->session()->get('account_version', 0) !== (int) $user->account_version;
        if ($user && (! $user->isAccountActive() || $expired)) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            abort(403, 'Akses akun berubah atau akun dinonaktifkan. Silakan masuk kembali.');
        }

        $customShop = $request->attributes->get('custom_domain_shop');
        if ($user && $customShop && (int) $user->shop_id !== (int) $customShop->id) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            abort(403, 'Akun Anda tidak terdaftar pada toko domain ini.');
        }

        return $next($request);
    }
}
