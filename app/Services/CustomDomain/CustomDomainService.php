<?php

namespace App\Services\CustomDomain;

use App\Mail\CustomDomainRequestedMail;
use App\Models\Shop;
use App\Models\ShopDomain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomDomainService
{
    public function __construct(private CloudflareForSaaSService $cloudflare) {}

    public function isSystemHost(string $host): bool
    {
        $normalized = $this->normalizeDomain($host);

        return in_array($normalized, array_map('strtolower', config('custom_domains.system_hosts', [])), true);
    }

    public function normalizeDomain(string $input): string
    {
        $value = trim(strtolower($input));
        $value = preg_replace('#^https?://#i', '', $value) ?? $value;
        $value = explode('/', $value)[0];
        $value = explode(':', $value)[0];

        return rtrim($value, '.');
    }

    public function validateDomainForShop(string $domain, Shop $shop): void
    {
        if ($domain === '' || strlen($domain) > 253 || ! str_contains($domain, '.')) {
            throw ValidationException::withMessages(['domain' => 'Masukkan nama domain yang valid, misalnya order.tokoanda.com.']);
        }

        if (filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            throw ValidationException::withMessages(['domain' => 'Alamat IP tidak dapat digunakan sebagai domain kustom.']);
        }

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw ValidationException::withMessages(['domain' => 'Format domain tidak valid. Gunakan huruf kecil, angka, tanda hubung (-), dan titik.']);
        }

        if ($this->isSystemHost($domain)) {
            throw ValidationException::withMessages(['domain' => 'Domain sistem OQARI tidak dapat digunakan sebagai domain kustom.']);
        }

        foreach ((array) config('custom_domains.blocked_suffixes', []) as $suffix) {
            $suffix = strtolower((string) $suffix);
            if ($suffix !== '' && (str_ends_with($domain, $suffix) || $domain === ltrim($suffix, '.'))) {
                throw ValidationException::withMessages(['domain' => 'Gunakan domain milik bisnis Anda sendiri, bukan subdomain OQARI atau lokal.']);
            }
        }

        $claimedByOther = ShopDomain::query()
            ->where('domain', $domain)
            ->where('shop_id', '!=', $shop->id)
            ->exists();

        if ($claimedByOther) {
            throw ValidationException::withMessages(['domain' => 'Domain ini sudah terdaftar pada bisnis lain.']);
        }
    }

    public function register(Shop $shop, string $rawDomain): ShopDomain
    {
        $domain = $this->normalizeDomain($rawDomain);
        $this->validateDomainForShop($domain, $shop);

        $record = DB::transaction(function () use ($shop, $domain): ShopDomain {
            $existing = ShopDomain::query()
                ->where('shop_id', $shop->id)
                ->where('domain', $domain)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            ShopDomain::query()
                ->where('shop_id', $shop->id)
                ->update(['is_primary' => false]);

            return ShopDomain::create([
                'shop_id' => $shop->id,
                'domain' => $domain,
                'verification_token' => Str::random(32),
                'status' => ShopDomain::STATUS_PENDING,
                'ssl_status' => 'pending',
                'is_primary' => true,
            ]);
        });

        $this->forgetCache($record->domain);

        return $record;
    }

    /**
     * @param  ?callable(string, int): array<int, array<string, mixed>>  $dnsLookup
     */
    public function verify(ShopDomain $shopDomain, ?callable $dnsLookup = null): ShopDomain
    {
        $lookup = $dnsLookup ?? static fn (string $host, int $type): array => @dns_get_record($host, $type) ?: [];

        $expectedTarget = strtolower((string) config('custom_domains.cname_target', 'oqariplus.appku.site'));
        $expectedIp = (string) config('custom_domains.origin_ip', '46.202.186.252');

        $cnameRecords = $lookup($shopDomain->domain, DNS_CNAME);
        $cnameMatched = collect($cnameRecords)->contains(function (array $record) use ($expectedTarget): bool {
            return strtolower(rtrim((string) ($record['target'] ?? ''), '.')) === $expectedTarget;
        });

        $aRecords = $cnameMatched ? [] : $lookup($shopDomain->domain, DNS_A);
        $allowedIps = [$expectedIp];
        if (! $cnameMatched) {
            foreach ($lookup($expectedTarget, DNS_A) as $targetRecord) {
                if (! empty($targetRecord['ip'])) {
                    $allowedIps[] = (string) $targetRecord['ip'];
                }
            }
        }
        $aMatched = collect($aRecords)->contains(function (array $record) use ($allowedIps): bool {
            return in_array((string) ($record['ip'] ?? ''), $allowedIps, true);
        });

        $pointingMatched = $cnameMatched || $aMatched;

        $txtRecords = $lookup($shopDomain->txtRecordName(), DNS_TXT);
        $expectedTxt = $shopDomain->txtRecordValue();
        $txtMatched = collect($txtRecords)->contains(function (array $record) use ($expectedTxt): bool {
            $txt = trim((string) ($record['txt'] ?? ''));

            return $txt === $expectedTxt;
        });

        if (! $pointingMatched || ! $txtMatched) {
            $missing = [];
            if (! $pointingMatched) {
                $missing[] = "CNAME {$shopDomain->domain} → {$expectedTarget} (atau A → {$expectedIp})";
            }
            if (! $txtMatched) {
                $missing[] = "TXT {$shopDomain->txtRecordName()} = {$expectedTxt}";
            }

            $shopDomain->update([
                'status' => ShopDomain::STATUS_FAILED,
                'last_checked_at' => now(),
                'last_error' => 'Record DNS belum sesuai: '.implode(' dan ', $missing).'.',
            ]);
            $this->forgetCache($shopDomain->domain);

            return $shopDomain->fresh();
        }

        $autoApprove = config('custom_domains.approval_mode') === 'auto' && $this->cloudflare->isConfigured();

        if (! $autoApprove) {
            $shopDomain->update([
                'status' => ShopDomain::STATUS_PENDING_APPROVAL,
                'ssl_status' => 'pending',
                'last_checked_at' => now(),
                'last_error' => null,
            ]);
            $this->forgetCache($shopDomain->domain);
            $this->notifyAdminOfRequest($shopDomain);

            return $shopDomain->fresh();
        }

        try {
            $cf = $this->cloudflare->createOrSyncHostname($shopDomain->domain);
            $shopDomain->update([
                'status' => ShopDomain::STATUS_ACTIVE,
                'cloudflare_hostname_id' => $cf['id'] ?? $shopDomain->cloudflare_hostname_id,
                'ssl_status' => $cf['ssl_status'] ?? 'active',
                'verified_at' => $shopDomain->verified_at ?? now(),
                'last_checked_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $shopDomain->update([
                'status' => ShopDomain::STATUS_FAILED,
                'last_checked_at' => now(),
                'last_error' => 'DNS cocok, tetapi sinkronisasi Cloudflare gagal: '.$e->getMessage(),
            ]);
        }

        $this->forgetCache($shopDomain->domain);

        return $shopDomain->fresh();
    }

    public function approve(ShopDomain $shopDomain): ShopDomain
    {
        $shopDomain->update([
            'status' => ShopDomain::STATUS_ACTIVE,
            'ssl_status' => 'active',
            'verified_at' => $shopDomain->verified_at ?? now(),
            'last_checked_at' => now(),
            'last_error' => null,
        ]);
        $this->forgetCache($shopDomain->domain);

        return $shopDomain->fresh();
    }

    public function reject(ShopDomain $shopDomain, ?string $reason = null): ShopDomain
    {
        $shopDomain->update([
            'status' => ShopDomain::STATUS_FAILED,
            'last_checked_at' => now(),
            'last_error' => $reason ?: 'Pengajuan domain ditolak oleh Admin OQARI Plus.',
        ]);
        $this->forgetCache($shopDomain->domain);

        return $shopDomain->fresh();
    }

    private function notifyAdminOfRequest(ShopDomain $shopDomain): void
    {
        $adminEmail = trim((string) config('custom_domains.admin_notification_email', ''));
        if ($adminEmail === '') {
            return;
        }

        try {
            Mail::to($adminEmail)->send(new CustomDomainRequestedMail($shopDomain));
        } catch (Throwable $e) {
            Log::warning('Gagal mengirim email notifikasi pengajuan domain kustom.', [
                'domain' => $shopDomain->domain,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function remove(ShopDomain $shopDomain): void
    {
        $domain = $shopDomain->domain;
        $hostnameId = $shopDomain->cloudflare_hostname_id;

        $shopDomain->delete();
        $this->forgetCache($domain);

        try {
            $this->cloudflare->deleteHostname($hostnameId);
        } catch (Throwable) {
            // Best-effort cleanup in Cloudflare
        }
    }

    public function resolveActiveShopByHost(string $host): ?Shop
    {
        $normalized = $this->normalizeDomain($host);
        if ($normalized === '' || $this->isSystemHost($normalized)) {
            return null;
        }

        $ttl = (int) config('custom_domains.cache_ttl_seconds', 300);

        $shopId = Cache::remember("custom_domain:{$normalized}", $ttl, function () use ($normalized): ?int {
            return ShopDomain::query()
                ->active()
                ->where('domain', $normalized)
                ->value('shop_id');
        });

        if (! $shopId) {
            return null;
        }

        $shop = Shop::query()->find($shopId);

        return $shop && ! $shop->isSuspended() ? $shop : null;
    }

    public function forgetCache(string $domain): void
    {
        Cache::forget('custom_domain:'.$this->normalizeDomain($domain));
    }
}
