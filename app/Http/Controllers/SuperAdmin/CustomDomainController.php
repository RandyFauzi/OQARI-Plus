<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ShopDomain;
use App\Models\SuperAdminAuditLog;
use App\Services\CustomDomain\CustomDomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomDomainController extends Controller
{
    public function __construct(private CustomDomainService $domains) {}

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
