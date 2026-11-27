<?php

namespace App\Services\CustomDomain;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareForSaaSService
{
    public function isConfigured(): bool
    {
        return (bool) config('custom_domains.cloudflare.enabled')
            && filled(config('custom_domains.cloudflare.api_token'))
            && filled(config('custom_domains.cloudflare.zone_id'));
    }

    /**
     * Register or sync a custom hostname in Cloudflare for SaaS.
     *
     * @return array{id: ?string, status: string, ssl_status: string, error: ?string}
     */
    public function createOrSyncHostname(string $domain): array
    {
        if (! $this->isConfigured()) {
            return [
                'id' => null,
                'status' => 'active',
                'ssl_status' => 'pending',
                'error' => null,
            ];
        }

        $zoneId = (string) config('custom_domains.cloudflare.zone_id');
        $token = (string) config('custom_domains.cloudflare.api_token');

        $existing = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}/custom_hostnames", [
                'hostname' => $domain,
            ])
            ->throw()
            ->json('result.0');

        if (is_array($existing) && isset($existing['id'])) {
            return [
                'id' => (string) $existing['id'],
                'status' => (string) ($existing['status'] ?? 'pending'),
                'ssl_status' => (string) ($existing['ssl']['status'] ?? 'pending_validation'),
                'error' => null,
            ];
        }

        $created = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/custom_hostnames", [
                'hostname' => $domain,
                'ssl' => [
                    'method' => 'txt',
                    'type' => 'dv',
                    'settings' => [
                        'min_tls_version' => '1.2',
                    ],
                ],
            ]);

        if (! $created->successful()) {
            $message = (string) ($created->json('errors.0.message') ?? 'Gagal mendaftarkan hostname ke Cloudflare.');
            throw new RuntimeException($message);
        }

        $result = $created->json('result') ?? [];

        return [
            'id' => isset($result['id']) ? (string) $result['id'] : null,
            'status' => (string) ($result['status'] ?? 'pending'),
            'ssl_status' => (string) ($result['ssl']['status'] ?? 'pending_validation'),
            'error' => null,
        ];
    }

    public function deleteHostname(?string $hostnameId): void
    {
        if (! $hostnameId || ! $this->isConfigured()) {
            return;
        }

        $zoneId = (string) config('custom_domains.cloudflare.zone_id');
        $token = (string) config('custom_domains.cloudflare.api_token');

        Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->delete("https://api.cloudflare.com/client/v4/zones/{$zoneId}/custom_hostnames/{$hostnameId}");
    }
}
