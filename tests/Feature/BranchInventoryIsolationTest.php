<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Actions\Inventory\AdjustStock;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\Table;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchInventoryIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_branch_has_isolated_stock_ledger_and_orders_deduct_only_their_branch_balance(): void
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $branches = app(ManageBranches::class);
        $branchA = $branches->enable($shop, 'Outlet Alpha');
        $branchB = $branches->create($shop, 'Outlet Bravo');

        $beans = RawMaterial::create([
            'shop_id' => $shop->id,
            'name' => 'Kopi Arabica',
            'unit' => 'gram',
            'current_stock' => 0,
            'minimum_stock' => 100,
            'cost_per_unit' => 250,
        ]);

        $espresso = Product::create([
            'shop_id' => $shop->id,
            'name' => 'Espresso',
            'price' => 20000,
            'cogs' => 5000,
        ]);

        ProductRecipe::create([
            'product_id' => $espresso->id,
            'raw_material_id' => $beans->id,
            'amount' => 20,
        ]);

        $context = app(BranchContext::class);
        $inventory = app(InventoryService::class);
        $adjust = app(AdjustStock::class);

        // 1. Stock Branch A with 1000g (Branch B still has 0g)
        $context->select($branchA);
        $adjust->execute($owner, [
            'raw_material_id' => $beans->id,
            'type' => 'in',
            'amount' => 1000,
            'notes' => 'Stok Awal Alpha',
        ]);
        $context->clear();

        $locA = $inventory->location($shop->id, InventoryLocation::Outlet, $branchA->id);
        $locB = $inventory->location($shop->id, InventoryLocation::Outlet, $branchB->id);
        $this->assertEquals(1000.0, $inventory->quantity($beans->fresh(), $locA));
        $this->assertEquals(0.0, $inventory->quantity($beans->fresh(), $locB));

        // 2. Order on Branch B fails because Branch B has 0g (must NOT fall back to Branch A or current_stock)
        $tableB = Table::create(['shop_id' => $shop->id, 'branch_id' => $branchB->id, 'name' => 'B1', 'public_token' => 'tokb1']);
        $this->postJson(route('shop.branch.order.submit', ['slug' => $shop->slug, 'branchSlug' => $branchB->slug]), [
            'customer_name' => 'Pelanggan B',
            'fulfillment_type' => 'DINE_IN',
            'table_token' => $tableB->public_token,
            'payment_method' => 'cash',
            'items' => [['id' => $espresso->id, 'qty' => 2]],
        ])->assertStatus(422);

        // 3. Stock Branch B with 500g, then place orders on Branch B (2 qty = 40g) and Branch A (5 qty = 100g)
        $context->select($branchB);
        $adjust->execute($owner, [
            'raw_material_id' => $beans->id,
            'type' => 'in',
            'amount' => 500,
            'notes' => 'Stok Awal Bravo',
        ]);
        $context->clear();

        $this->postJson(route('shop.branch.order.submit', ['slug' => $shop->slug, 'branchSlug' => $branchB->slug]), [
            'customer_name' => 'Pelanggan B',
            'fulfillment_type' => 'TAKEAWAY',
            'payment_method' => 'cash',
            'items' => [['id' => $espresso->id, 'qty' => 2]],
        ])->assertOk();

        $this->postJson(route('shop.branch.order.submit', ['slug' => $shop->slug, 'branchSlug' => $branchA->slug]), [
            'customer_name' => 'Pelanggan A',
            'fulfillment_type' => 'TAKEAWAY',
            'payment_method' => 'cash',
            'items' => [['id' => $espresso->id, 'qty' => 5]],
        ])->assertOk();

        // Branch A = 900g (primary outlet), Branch B = 460g (isolated secondary outlet)
        $this->assertEquals(900.0, $inventory->quantity($beans->fresh(), $locA));
        $this->assertEquals(460.0, $inventory->quantity($beans->fresh(), $locB));
        $this->assertEquals(900.0, (float) $beans->fresh()->current_stock);

        // 4. Inventory API per branch returns only that branch's balance and mutations
        $invA = $this->actingAs($owner)
            ->getJson("/cabang/{$branchA->slug}/admin/api/inventory")
            ->assertOk();
        $this->assertEquals(900.0, (float) $invA->json('materials.0.current_stock'));
        $this->assertTrue(collect($invA->json('mutations'))->every(fn ($m) => (int) $m['branch_id'] === $branchA->id));

        $invB = $this->actingAs($owner)
            ->getJson("/cabang/{$branchB->slug}/admin/api/inventory")
            ->assertOk();
        $this->assertEquals(460.0, (float) $invB->json('materials.0.current_stock'));
        $this->assertTrue(collect($invB->json('mutations'))->every(fn ($m) => (int) $m['branch_id'] === $branchB->id));
    }
}
