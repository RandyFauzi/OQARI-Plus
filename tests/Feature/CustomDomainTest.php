<?php

namespace Tests\Feature;

use App\Mail\CustomDomainRequestedMail;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDomain;
use App\Models\User;
use App\Services\CustomDomain\CustomDomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_access_custom_domain_settings(): void
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $manager = User::factory()->create(['shop_id' => $shop->id, 'role' => 'manager']);
        $cashier = User::factory()->create(['shop_id' => $shop->id, 'role' => 'cashier']);

        $this->actingAs($manager)->get(route('admin.custom-domain.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.custom-domain.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.custom-domain.index'))
            ->assertOk()
            ->assertSee('Domain Kustom')
            ->assertSee('Cara Menghubungkan Domain Kustom');
    }

    public function test_owner_can_register_domain_and_system_or_duplicate_domains_are_rejected(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $ownerA = User::factory()->create(['shop_id' => $shopA->id, 'role' => 'owner']);
        $ownerB = User::factory()->create(['shop_id' => $shopB->id, 'role' => 'owner']);

        $this->actingAs($ownerA)->post(route('admin.custom-domain.store'), [
            'domain' => 'https://order.burgerkat.com/',
        ])->assertRedirect();

        $this->assertDatabaseHas('shop_domains', [
            'shop_id' => $shopA->id,
            'domain' => 'order.burgerkat.com',
            'status' => ShopDomain::STATUS_PENDING,
        ]);

        // System domain rejected
        $this->actingAs($ownerA)->post(route('admin.custom-domain.store'), [
            'domain' => 'sub.appku.site',
        ])->assertSessionHasErrors('domain');

        // IP rejected
        $this->actingAs($ownerA)->post(route('admin.custom-domain.store'), [
            'domain' => '192.168.1.1',
        ])->assertSessionHasErrors('domain');

        // Cross-tenant duplicate rejected
        $this->actingAs($ownerB)->post(route('admin.custom-domain.store'), [
            'domain' => 'order.burgerkat.com',
        ])->assertSessionHasErrors('domain');
    }

    public function test_manual_hostinger_approval_notifies_admin_email_and_superadmin_dashboard(): void
    {
        Mail::fake();

        config([
            'custom_domains.enabled' => true,
            'custom_domains.approval_mode' => 'manual',
            'custom_domains.admin_notification_email' => 'randyfauzi24@gmail.com',
            'custom_domains.cname_target' => 'oqariplus.appku.site',
            'custom_domains.origin_ip' => '46.202.186.252',
        ]);

        $shop = Shop::factory()->create(['name' => 'Burgerkat Hostinger', 'slug' => 'burgerkat-hostinger']);
        $superadmin = User::factory()->create(['shop_id' => null, 'role' => 'superadmin']);
        Product::create([
            'shop_id' => $shop->id,
            'category_name' => 'Burgers',
            'name' => 'Smash Burger Hostinger',
            'price' => 35000,
        ]);

        $service = app(CustomDomainService::class);
        $record = $service->register($shop, 'order.burgerkat.com');

        $verified = $service->verify($record, function (string $host, int $type) use ($record): array {
            if ($type === DNS_A && $host === 'order.burgerkat.com') {
                return [['ip' => '46.202.186.252']];
            }
            if ($type === DNS_TXT && $host === $record->txtRecordName()) {
                return [['txt' => $record->txtRecordValue()]];
            }

            return [];
        });

        // Must NOT auto-activate in manual mode
        $this->assertTrue($verified->isPendingApproval());
        $this->assertFalse($verified->isActive());

        Mail::assertSent(CustomDomainRequestedMail::class, function (CustomDomainRequestedMail $mail): bool {
            return $mail->hasTo('randyfauzi24@gmail.com') && $mail->shopDomain->domain === 'order.burgerkat.com';
        });

        // Domain is still 404 before SuperAdmin approves
        $this->get('http://order.burgerkat.com/')->assertNotFound();

        // SuperAdmin sees notification on dashboard and approves it
        $this->actingAs($superadmin)
            ->get('http://localhost/superadmin/dashboard')
            ->assertOk()
            ->assertSee('Notifikasi Pengajuan Domain Kustom')
            ->assertSee('order.burgerkat.com');

        $this->actingAs($superadmin)
            ->post('http://localhost/superadmin/custom-domains/'.$verified->id.'/approve')
            ->assertRedirect();

        $this->assertTrue($verified->fresh()->isActive());

        // Now storefront is live on custom domain
        $this->get('http://order.burgerkat.com/')
            ->assertOk()
            ->assertSee('Smash Burger Hostinger');
    }

    public function test_dns_verification_syncs_cloudflare_and_serves_storefront_on_custom_domain(): void
    {
        config([
            'custom_domains.enabled' => true,
            'custom_domains.approval_mode' => 'auto',
            'custom_domains.cname_target' => 'cname.oqariplus.appku.site',
            'custom_domains.cloudflare.enabled' => true,
            'custom_domains.cloudflare.zone_id' => 'zone-123',
            'custom_domains.cloudflare.api_token' => 'cf-token-abc',
        ]);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames*' => Http::sequence()
                ->push(['success' => true, 'result' => []], 200)
                ->push([
                    'success' => true,
                    'result' => [
                        'id' => 'cf-host-999',
                        'status' => 'active',
                        'ssl' => ['status' => 'active'],
                    ],
                ], 201)
                ->push(['success' => true], 200),
        ]);

        $shop = Shop::factory()->create(['name' => 'Burgerkat Custom', 'slug' => 'burgerkat-custom']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        Product::create([
            'shop_id' => $shop->id,
            'category_name' => 'Burgers',
            'name' => 'Smash Burger Custom',
            'price' => 35000,
        ]);

        $service = app(CustomDomainService::class);
        $record = $service->register($shop, 'order.burgerkat.com');

        $verified = $service->verify($record, function (string $host, int $type) use ($record): array {
            if ($type === DNS_CNAME && $host === 'order.burgerkat.com') {
                return [['target' => 'cname.oqariplus.appku.site']];
            }
            if ($type === DNS_TXT && $host === $record->txtRecordName()) {
                return [['txt' => $record->txtRecordValue()]];
            }

            return [];
        });

        $this->assertTrue($verified->isActive());
        $this->assertSame('cf-host-999', $verified->cloudflare_hostname_id);
        $this->assertSame('https://order.burgerkat.com', $shop->storefrontBaseUrl());

        // Custom domain renders shop storefront at root /
        $this->get('http://order.burgerkat.com/')
            ->assertOk()
            ->assertSee('Smash Burger Custom');

        // Superadmin routes are blocked on custom domain
        $this->get('http://order.burgerkat.com/superadmin/dashboard')
            ->assertNotFound();

        // Unknown custom domain returns 404
        $this->get('http://unknown-tenant.com/')
            ->assertNotFound();

        // Deleting domain from main host cleans up Cloudflare hostname
        $this->actingAs($owner)->delete('http://localhost/admin/custom-domain/'.$verified->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('shop_domains', ['id' => $verified->id]);
    }
}
