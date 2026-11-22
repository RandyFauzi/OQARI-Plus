<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Shop;
use App\Models\Table;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\BranchStorefrontResolver;
use App\Services\Receipt\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchStorefrontSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_inherits_owner_defaults_and_overrides_only_changed_variables(): void
    {
        [$owner, $branchA, $branchB] = $this->business();
        $shop = $owner->shop;
        $shop->update([
            'primary_color' => '#1e5a7a',
            'theme_style' => 'grid',
            'slogan' => 'Kopi Bisnis Pusat',
            'font_family' => 'DM Sans',
        ]);

        // 1. Both branches initially inherit Owner defaults
        $resolver = app(BranchStorefrontResolver::class);
        $this->assertSame('#1e5a7a', $resolver->apply($shop, $branchA)->primary_color);
        $this->assertSame('Kopi Bisnis Pusat', $resolver->apply($shop, $branchA)->slogan);
        $this->assertSame([], $resolver->overriddenKeys($branchA));

        // 2. Branch A overrides primary_color & slogan, leaving theme_style & font_family as Owner defaults
        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/settings", [
                'name' => $shop->name,
                'primary_color' => '#df5826',
                'slogan' => 'Cabang Kemang Spesial',
                'theme_style' => 'grid',
                'font_family' => 'DM Sans',
            ])
            ->assertOk()
            ->assertJsonPath('is_branch_mode', true)
            ->assertJsonPath('shop.primary_color', '#df5826')
            ->assertJsonPath('shop.slogan', 'Cabang Kemang Spesial');

        $branchA->refresh();
        $branchB->refresh();
        $shop->refresh();

        // Owner's shop row and Branch B remain untouched
        $this->assertSame('#1e5a7a', $shop->primary_color);
        $this->assertSame('Kopi Bisnis Pusat', $shop->slogan);
        $this->assertEqualsCanonicalizing(['primary_color', 'slogan'], $resolver->overriddenKeys($branchA));
        $this->assertSame('#1e5a7a', $resolver->apply($shop, $branchB)->primary_color);

        // 3. Owner updates theme_style & primary_color globally:
        // Branch A inherits new theme_style ('bistro'), keeps its overridden primary_color ('#df5826')
        // Branch B inherits BOTH new theme_style and new primary_color!
        app(BranchContext::class)->clear();
        $this->actingAs($owner)
            ->postJson('/admin/api/settings', [
                'name' => $shop->name,
                'primary_color' => '#164a35',
                'theme_style' => 'bistro',
            ])
            ->assertOk();

        $shop->refresh();
        $resolvedA = $resolver->apply($shop, $branchA);
        $resolvedB = $resolver->apply($shop, $branchB);
        $this->assertSame('#df5826', $resolvedA->primary_color);
        $this->assertSame('bistro', $resolvedA->theme_style);
        $this->assertSame('#164a35', $resolvedB->primary_color);
        $this->assertSame('bistro', $resolvedB->theme_style);

        // 4. Branch A resets to business defaults
        $this->actingAs($owner)
            ->postJson(route('branch.admin.settings.reset-branch', ['operatingBranch' => $branchA->slug]))
            ->assertOk()
            ->assertJsonPath('branch_overridden_keys', [])
            ->assertJsonPath('shop.primary_color', '#164a35');

        $this->assertNull($branchA->fresh()->settings);
    }

    public function test_branch_operating_hours_and_customer_menu_reflect_effective_branch_settings(): void
    {
        [$owner, $branchA, $branchB] = $this->business();
        $shop = $owner->shop;
        $shop->update(['slogan' => 'Slogan Pusat', 'primary_color' => '#1e5a7a']);

        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/operating-hours", [
                'operating_hours' => ['monday' => ['open' => '07:00', 'close' => '23:00', 'is_open' => true]],
            ])
            ->assertOk()
            ->assertJsonPath('is_branch_mode', true);

        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/settings", [
                'name' => $shop->name,
                'slogan' => 'Khusus Cabang Alpha',
                'primary_color' => '#c54a1e',
            ])
            ->assertOk();

        app(BranchContext::class)->clear();
        $tableA = Table::create(['shop_id' => $shop->id, 'branch_id' => $branchA->id, 'name' => 'Meja A1', 'public_token' => 'toka1']);
        $tableB = Table::create(['shop_id' => $shop->id, 'branch_id' => $branchB->id, 'name' => 'Meja B1', 'public_token' => 'tokb1']);

        $this->get(route('shop.menu', ['slug' => $shop->slug, 't' => $tableA->public_token]))
            ->assertOk()
            ->assertSee('Khusus Cabang Alpha');

        $this->get(route('shop.menu', ['slug' => $shop->slug, 't' => $tableB->public_token]))
            ->assertOk()
            ->assertSee('Slogan Pusat')
            ->assertDontSee('Khusus Cabang Alpha');
    }

    public function test_receipt_service_and_print_endpoints_use_branch_overrides(): void
    {
        [$owner, $branchA, $branchB] = $this->business();
        $shop = $owner->shop;
        $shop->update([
            'receipt_header' => 'Header Pusat',
            'receipt_footer' => 'Footer Pusat',
            'receipt_settings' => ['paper_width' => '58mm', 'show_address' => true],
        ]);

        $this->actingAs($owner)
            ->postJson("/cabang/{$branchB->slug}/admin/api/settings", [
                'name' => 'Cafe Bravo',
                'address' => 'Jl. Bravo No. 99',
                'receipt_header' => 'Header Khusus Bravo',
                'receipt_footer' => 'Terima Kasih Bravo',
                'receipt_settings' => ['paper_width' => '80mm', 'show_address' => true],
            ])
            ->assertOk();

        $orderB = Order::create([
            'shop_id' => $shop->id,
            'branch_id' => $branchB->id,
            'customer_name' => 'Andi',
            'fulfillment_type' => 'TAKEAWAY',
            'payment_method' => 'cash',
            'payment_status' => 'PAID',
            'order_status' => 'CONFIRMED',
            'subtotal' => 25000,
            'grand_total' => 25000,
        ]);

        $receiptB = app(ReceiptService::class)->build($orderB);
        $this->assertSame('Cafe Bravo', $receiptB->shop['name']);
        $this->assertSame('Jl. Bravo No. 99', $receiptB->shop['address']);
        $this->assertSame('Header Khusus Bravo', $receiptB->shop['receipt_header']);
        $this->assertSame('Terima Kasih Bravo', $receiptB->shop['receipt_footer']);
        $this->assertSame('80mm', $receiptB->receiptSettings['paper_width'] ?? null);

        $this->actingAs($owner)
            ->get("/cabang/{$branchB->slug}/admin/api/orders/{$orderB->id}/print")
            ->assertOk()
            ->assertSee('Header Khusus Bravo')
            ->assertSee('Terima Kasih Bravo');

        $this->actingAs($owner)
            ->get("/cabang/{$branchB->slug}/admin/api/orders/sample/test-print")
            ->assertOk()
            ->assertSee('Header Khusus Bravo')
            ->assertSee('Terima Kasih Bravo');

        $this->actingAs($owner)
            ->get("/cabang/{$branchA->slug}/admin/api/orders/sample/test-print")
            ->assertOk()
            ->assertSee('Header Pusat')
            ->assertDontSee('Header Khusus Bravo');
    }

    /** @return array{User, Branch, Branch} */
    private function business(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $branches = app(ManageBranches::class);

        return [$owner, $branches->enable($shop, 'Outlet Alpha'), $branches->create($shop, 'Outlet Bravo')];
    }
}
