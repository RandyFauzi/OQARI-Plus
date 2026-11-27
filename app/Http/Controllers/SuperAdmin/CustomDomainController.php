<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ShopDomain;
use App\Models\SuperAdminAuditLog;
use App\Services\CustomDomain\CustomDomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomDomainController extends Controller
{
    public function __construct(private CustomDomainService $domains) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');

        $query = ShopDomain::with('shop')
            ->when(
                in_array($status, [
                    ShopDomain::STATUS_PENDING_APPROVAL,
                    ShopDomain::STATUS_ACTIVE,
                    ShopDomain::STATUS_PENDING,
                    ShopDomain::STATUS_FAILED,
                ], true),
                fn ($q) => $q->where('status', $status),
            )
            ->orderByRaw("CASE WHEN status = 'pending_approval' THEN 0 WHEN status = 'pending' THEN 1 WHEN status = 'failed' THEN 2 ELSE 3 END")
            ->latest('id');

        $domains = $query->paginate(20)->withQueryString();
        $counts = [
            'all' => ShopDomain::count(),
            'pending_approval' => ShopDomain::where('status', ShopDomain::STATUS_PENDING_APPROVAL)->count(),
            'active' => ShopDomain::where('status', ShopDomain::STATUS_ACTIVE)->count(),
            'pending' => ShopDomain::where('status', ShopDomain::STATUS_PENDING)->count(),
            'failed' => ShopDomain::where('status', ShopDomain::STATUS_FAILED)->count(),
        ];

        return view('SuperAdmin.custom-domains.index', compact('domains', 'counts', 'status'));
    }

    public function approve(Request $request, int $domain): RedirectResponse
    {
        $record = ShopDomain::with('shop')->findOrFail($domain);
        $this->domains->approve($record);

        SuperAdminAuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'custom_domain.approved',
            'target_type' => ShopDomain::class,
            'target_id' => $record->id,
            'target_label' => "{$record->domain} ({$record->shop?->name})",
        ]);

        return back()->with('success', "Domain {$record->domain} disetujui dan sekarang aktif.");
    }

    public function reject(Request $request, int $domain): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $record = ShopDomain::with('shop')->findOrFail($domain);
        $this->domains->reject($record, $data['reason'] ?? null);

        SuperAdminAuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'custom_domain.rejected',
            'target_type' => ShopDomain::class,
            'target_id' => $record->id,
            'target_label' => "{$record->domain} ({$record->shop?->name})",
        ]);

        return back()->with('success', "Pengajuan domain {$record->domain} ditolak.");
    }
}
