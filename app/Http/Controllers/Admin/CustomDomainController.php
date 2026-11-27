<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\CustomDomain\CustomDomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomDomainController extends Controller
{
    public function __construct(private CustomDomainService $domains) {}

    public function index(Request $request): View
    {
        $shop = $this->ownerShop($request);
        $domains = $shop->domains()->latest('id')->get();
        $cnameTarget = (string) config('custom_domains.cname_target');
        $cloudflareEnabled = (bool) config('custom_domains.cloudflare.enabled');

        return view('Admin.CustomDomain.index', compact('shop', 'domains', 'cnameTarget', 'cloudflareEnabled'));
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $this->ownerShop($request);
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
        ]);

        $record = $this->domains->register($shop, $data['domain']);

        return back()->with('success', "Domain {$record->domain} ditambahkan. Pasang CNAME & TXT di DNS Anda, lalu klik Verifikasi DNS.");
    }

    public function verify(Request $request, int $domain): RedirectResponse
    {
        $shop = $this->ownerShop($request);
        $record = $shop->domains()->findOrFail($domain);
        $verified = $this->domains->verify($record);

        if ($verified->isActive()) {
            return back()->with('success', "Domain {$verified->domain} aktif dan terhubung ke toko Anda.");
        }

        if ($verified->isPendingApproval()) {
            return back()->with('success', "DNS {$verified->domain} terverifikasi! Pengajuan telah dikirim ke Admin OQARI Plus untuk diaktifkan di server.");
        }

        return back()->withErrors(['domain' => $verified->last_error ?: 'Verifikasi DNS belum berhasil.']);
    }

    public function destroy(Request $request, int $domain): RedirectResponse
    {
        $shop = $this->ownerShop($request);
        $record = $shop->domains()->findOrFail($domain);
        $domainName = $record->domain;

        $this->domains->remove($record);

        return back()->with('success', "Domain {$domainName} berhasil dihapus.");
    }

    private function ownerShop(Request $request): Shop
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'owner' && $user->shop_id, 403);

        return $user->shop()->firstOrFail();
    }
}
