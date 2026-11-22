<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranch
{
    public function __construct(private BranchContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->context;
        $user = $request->user();
        $slug = $request->route('operatingBranch');
        $request->route()->forgetParameter('operatingBranch');
        $context->clear();
        $shop = $user?->shop;
        if (! $shop?->branches_enabled) {
            abort_if($slug !== null, 404);

            return $next($request);
        }

        if ($slug === null && $user->role === 'owner' && $request->routeIs(
            'admin.purchases.*', 'admin.production.*', 'admin.menu.*', 'admin.addons.*', 'admin.hpp.*',
            'admin.users.*', 'admin.settings.*', 'admin.vouchers.*', 'admin.receipt-settings.*', 'admin.operating-hours.*',
        )) {
            return $next($request);
        }

        $available = $context->accessible($user);
        $branch = $slug !== null
            ? $available->where('slug', $slug)->firstOrFail()
            : $available->orderByDesc('is_primary')->orderBy('id')->first();

        if ($user->role !== 'owner') {
            abort_unless($branch, 403, 'Akun ini belum ditugaskan ke cabang aktif.');
        }
        if ($branch instanceof Branch) {
            $role = $user->role === 'owner' ? 'owner' : $branch->memberships()->where('user_id', $user->id)->value('role');
            abort_unless($role === 'owner' || array_key_exists($role ?? '', config('access.roles')), 403);
            $context->select($branch, $role, $user->id);
        }

        return $next($request);
    }
}
