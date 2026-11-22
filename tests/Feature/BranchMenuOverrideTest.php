<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Actions\POS\CalculateCart;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Table;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\BranchMenuResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BranchMenuOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_can_override_menu_price_discount_and_availability_without_affecting_master(): void
    {
        [$owner, $branchA, $branchB] = $this->business();
        $shop = $owner->shop;

        $burger = Product::create([
            'shop_id' => $shop->id,
            'name' => 'Burger Jawara',
            'price' => 25000,
            'discount_price' => 20000,
            'is_discount_active' => false,
            'cogs' => 10000,
            'is_sold_out' => false,
        ]);
        $coffee = Product::create([
            'shop_id' => $shop->id,
            'name' => 'Kopi Susu',
            'price' => 18000,
            'cogs' => 6000,
            'is_sold_out' => false,
        ]);

        // 1. Branch A toggles sold_out on Coffee -> only Branch A is sold out, Master & Branch B stay available
        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/menu/{$coffee->id}/toggle")
            ->assertOk()
            ->assertJsonPath('is_sold_out', true)
            ->assertJsonPath('is_branch_mode', true);

        $this->assertFalse((bool) $coffee->fresh()->is_sold_out);

        // 2. Branch A sets custom price (30000) on Burger and hides Coffee (is_available = false)
        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/menu/{$burger->id}/branch-override", [
                'is_available' => true,
                'price_override' => 30000,
                'discount_price_override' => 26000,
                'is_discount_active_override' => true,
            ])
            ->assertOk()
            ->assertJsonPath('product.has_branch_override', true);

        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/menu/{$coffee->id}/branch-override", [
                'is_available' => false,
            ])
            ->assertOk();

        // 3. Customer Menu via Table A shows Burger at Rp 26.000 (discount) and hides Coffee
        app(BranchContext::class)->clear();
        $tableA = Table::create(['shop_id' => $shop->id, 'branch_id' => $branchA->id, 'name' => 'Meja A', 'public_token' => 'mja']);
        $tableB = Table::create(['shop_id' => $shop->id, 'branch_id' => $branchB->id, 'name' => 'Meja B', 'public_token' => 'mjb']);

        $this->get(route('shop.menu', ['slug' => $shop->slug, 't' => $tableA->public_token]))
            ->assertOk()
            ->assertSee('Burger Jawara')
            ->assertSee('26000')
            ->assertDontSee('Kopi Susu');

        // Table B still inherits Master Catalog (Burger 25000, Coffee 18000)
        $this->get(route('shop.menu', ['slug' => $shop->slug, 't' => $tableB->public_token]))
            ->assertOk()
            ->assertSee('Burger Jawara')
            ->assertSee('25000')
            ->assertSee('Kopi Susu');

        // 4. POS CalculateCart respects Branch A override price (26.000) and rejects Coffee
        $context = app(BranchContext::class);
        $context->select($branchA);
        $cart = app(CalculateCart::class)->execute($shop, [['id' => $burger->id, 'qty' => 2]]);
        $this->assertEquals(52000, $cart['subtotal']);

        try {
            app(CalculateCart::class)->execute($shop, [['id' => $coffee->id, 'qty' => 1]]);
            $this->fail('Expected ValidationException for unavailable branch product.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        // 5. Reset Branch A override restores Master Catalog price (25.000)
        $this->actingAs($owner)
            ->deleteJson("/cabang/{$branchA->slug}/admin/api/menu/{$burger->id}/branch-override")
            ->assertOk()
            ->assertJsonPath('product.has_branch_override', false);

        $resolved = app(BranchMenuResolver::class)->applyToProduct($burger->fresh(), $branchA->fresh());
        $this->assertEquals(25000, (float) $resolved->price);
        $this->assertFalse((bool) $resolved->is_discount_active);
    }

    public function test_branch_override_validation_and_dashboard_products_payload(): void
    {
        [$owner, $branchA] = $this->business();
        $product = Product::create([
            'shop_id' => $owner->shop_id,
            'name' => 'Matcha Latte',
            'price' => 24000,
            'cogs' => 9000,
            'is_sold_out' => false,
        ]);

        // Discount >= base price must return 422
        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/menu/{$product->id}/branch-override", [
                'is_available' => true,
                'price_override' => 28000,
                'discount_price_override' => 28000,
            ])
            ->assertStatus(422);

        // Save valid override
        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/menu/{$product->id}/branch-override", [
                'is_available' => true,
                'price_override' => 28000,
                'discount_price_override' => 25000,
                'is_discount_active_override' => true,
            ])
            ->assertOk()
            ->assertJsonPath('product.master_price', 24000)
            ->assertJsonPath('product.branch_override.price_override', '28000.00');

        // Dashboard products API exposes master_price & branch_override
        $this->actingAs($owner)
            ->getJson("/cabang/{$branchA->slug}/admin/api/dashboard/products?per_page=10")
            ->assertOk()
            ->assertJsonPath('data.0.has_branch_override', true)
            ->assertJsonPath('data.0.branch_override.discount_price_override', '25000.00');

        // Saving all-default values removes override row cleanly
        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/menu/{$product->id}/branch-override", [
                'is_available' => true,
                'price_override' => null,
                'discount_price_override' => null,
                'is_discount_active_override' => null,
            ])
            ->assertOk()
            ->assertJsonPath('product.has_branch_override', false)
            ->assertJsonPath('product.branch_override', null);
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
