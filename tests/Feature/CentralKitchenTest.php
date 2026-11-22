<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\ProductionRecipe;
use App\Models\ProductionWorkOrder;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CentralKitchenTest extends TestCase
{
    use RefreshDatabase;

    public function test_burgerkat_uses_locked_recipe_version_and_books_good_yield_once(): void
    {
        [$owner, $sauce, $base] = $this->shop();
        $this->actingAs($owner)->get(route('admin.production.index'))->assertOk()->assertSee('Tambah versi resep');
        $recipe = $this->createRecipe($sauce, $base);
        $order = $this->createOrder($recipe, 5000);
        $secondVersion = $this->createRecipe($sauce, $base, 4100);
        $this->assertSame(2, $secondVersion->version);
        $this->assertSame($recipe->id, $order->production_recipe_id);
        $this->assertSame('4000.000000', $order->items()->sole()->planned_quantity);
        $this->get(route('admin.production.show', $order))->assertOk()->assertSee('Resep versi 1')->assertSee('Saus Burgerkat');
        $this->get(route('admin.inventory.locations.index'))->assertOk()->assertSee('Central Kitchen');
        $this->shipToKitchen($base, 4000);

        $resultUrl = route('admin.production.result', $order);
        $postUrl = route('admin.production.post', $order);
        $this->postJson($postUrl, ['revision' => 0])->assertUnprocessable();
        $result = $this->resultPayload($order, 4600, 400);
        $this->postJson($resultUrl, $result)->assertUnprocessable()->assertJsonValidationErrors('waste_reason');
        $result['waste_reason'] = 'Saus tertinggal di alat pencampur';
        $this->postJson($resultUrl, $result)->assertRedirect();
        $this->postJson($resultUrl, $result)->assertRedirect();
        $this->assertSame(1, $order->fresh()->result_revision);
        $this->assertDatabaseMissing('stock_mutations', ['reference_type' => 'ProductionWorkOrder', 'reference_id' => $order->id]);
        $this->postJson($postUrl, ['revision' => 0])->assertUnprocessable();
        $this->postJson($postUrl, ['revision' => 1])->assertRedirect();
        $this->postJson($postUrl, ['revision' => 1])->assertRedirect();
        $this->postJson($resultUrl, $result)->assertUnprocessable();

        $order->refresh();
        $this->assertSame(ProductionWorkOrder::Posted, $order->status);
        $this->assertSame('400.000000', $order->waste_quantity);
        $this->assertSame('142600.000000', $order->total_cost);
        $this->assertSame('31.000000', $order->output_unit_cost);
        $this->assertBalance($base, InventoryLocation::Kitchen, 0);
        $this->assertBalance($sauce, InventoryLocation::Kitchen, 4600);
        $this->assertSame('3000.000000', $sauce->fresh()->current_stock);
        $this->assertDatabaseHas('stock_mutations', [
            'reference_type' => 'ProductionWorkOrder', 'reference_id' => $order->id,
            'inventory_location_id' => $order->inventory_location_id, 'raw_material_id' => $sauce->id, 'amount' => 4600, 'unit_cost' => 31,
        ]);
        $this->assertSame(2, StockMutation::where('reference_type', 'ProductionWorkOrder')->count());
        $this->get(route('admin.production.show', $order))->assertOk()->assertSee('Dibukukan')->assertSee('Saus tertinggal di alat pencampur');

        $this->send($sauce, 600, 'kitchen-outlet');
        $this->assertBalance($sauce, InventoryLocation::Kitchen, 4000);
        $this->assertBalance($sauce, InventoryLocation::Outlet, 3600);
    }

    public function test_insufficient_kitchen_stock_rolls_back_every_movement_and_keeps_review_open(): void
    {
        [$owner, $sauce, $base] = $this->shop();
        $seasoning = RawMaterial::create(['shop_id' => $owner->shop_id, 'name' => 'Bumbu', 'unit' => 'g', 'cost_per_unit' => 12]);
        $this->actingAs($owner);
        $recipe = $this->createRecipe($sauce, $base, 4000, [['raw_material_id' => $seasoning->id, 'quantity' => 15]]);
        $order = $this->createOrder($recipe, 5000);
        $this->shipToKitchen($base, 4000);
        $this->postJson(route('admin.production.result', $order), $this->resultPayload($order, 5000, 0))->assertRedirect();
        $this->postJson(route('admin.production.post', $order), ['revision' => 1])->assertUnprocessable();

        $this->assertSame(ProductionWorkOrder::Review, $order->fresh()->status);
        $this->assertBalance($base, InventoryLocation::Kitchen, 4000);
        $this->assertDatabaseMissing('stock_mutations', ['reference_type' => 'ProductionWorkOrder', 'reference_id' => $order->id]);
    }

    public function test_all_waste_books_consumption_without_adding_finished_sauce(): void
    {
        [$owner, $sauce, $base] = $this->shop();
        $this->actingAs($owner);
        $recipe = $this->createRecipe($sauce, $base);
        $order = $this->createOrder($recipe, 5000);
        $this->shipToKitchen($base, 4000);
        $result = $this->resultPayload($order, 0, 5000);
        $result['waste_reason'] = 'Batch tidak lolos inspeksi';
        $this->postJson(route('admin.production.result', $order), $result)->assertRedirect();
        $this->postJson(route('admin.production.post', $order), ['revision' => 1])->assertRedirect();

        $this->assertSame(ProductionWorkOrder::Posted, $order->fresh()->status);
        $this->assertNull($order->fresh()->output_unit_cost);
        $this->assertBalance($base, InventoryLocation::Kitchen, 0);
        $this->assertDatabaseMissing('stock_mutations', ['reference_type' => 'ProductionWorkOrder', 'raw_material_id' => $sauce->id]);
    }

    public function test_production_permissions_and_shop_isolation_are_enforced(): void
    {
        [$owner, $sauce, $base] = $this->shop();
        $other = User::factory()->create(['shop_id' => Shop::factory(), 'role' => 'owner']);
        $other->shop->update(['onboarding_status' => 'completed']);
        $operator = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'crew']);
        $operator->forceFill(['permissions' => ['view-production', 'manage-production-orders']])->save();
        $recipe = ProductionRecipe::factory()->create(['shop_id' => $owner->shop_id, 'output_material_id' => $sauce->id]);
        $recipe->items()->create(['raw_material_id' => $base->id, 'quantity' => 4000, 'unit' => 'ml']);
        $this->actingAs($operator);
        $order = $this->createOrder($recipe, 2500);
        $this->assertSame('2000.000000', $order->items()->sole()->planned_quantity);
        $this->postJson(route('admin.production.result', $order), $this->resultPayload($order, 2400, 0))->assertRedirect();
        $this->postJson(route('admin.production.post', $order), ['revision' => 1])->assertForbidden();

        $this->actingAs($other)->get(route('admin.production.show', $order))->assertNotFound();
        $this->postJson(route('admin.production.post', $order), ['revision' => 1])->assertNotFound();
        $this->postJson(route('admin.production.work-order'), [
            'request_id' => (string) Str::uuid(), 'production_recipe_id' => $recipe->id, 'target_quantity' => 2500,
        ])->assertNotFound();
        $this->assertDatabaseCount('production_work_orders', 1);
    }

    /** @return array{User, RawMaterial, RawMaterial} */
    private function shop(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed', 'name' => 'Burgerkat Demo']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $sauce = RawMaterial::create(['shop_id' => $shop->id, 'name' => 'Saus Burgerkat', 'unit' => 'ml', 'current_stock' => 3000, 'cost_per_unit' => 32]);
        $base = RawMaterial::create(['shop_id' => $shop->id, 'name' => 'Basis Saus', 'unit' => 'ml', 'current_stock' => 0, 'cost_per_unit' => 35.65]);

        return [$owner, $sauce, $base];
    }

    private function createRecipe(RawMaterial $sauce, RawMaterial $base, int $quantity = 4000, array $additional = []): ProductionRecipe
    {
        $this->postJson(route('admin.production.recipe'), [
            'request_id' => (string) Str::uuid(), 'output_material_id' => $sauce->id, 'yield_quantity' => 5000,
            'items' => array_merge([['raw_material_id' => $base->id, 'quantity' => $quantity]], $additional),
        ])->assertRedirect(route('admin.production.index'));

        return ProductionRecipe::latest('id')->firstOrFail();
    }

    private function createOrder(ProductionRecipe $recipe, int $target): ProductionWorkOrder
    {
        $this->postJson(route('admin.production.work-order'), [
            'request_id' => (string) Str::uuid(), 'production_recipe_id' => $recipe->id, 'target_quantity' => $target,
        ])->assertRedirect();

        return ProductionWorkOrder::latest('id')->firstOrFail();
    }

    private function resultPayload(ProductionWorkOrder $order, int $good, int $waste): array
    {
        return [
            'revision' => 0, 'good_quantity' => $good, 'waste_quantity' => $waste,
            'items' => $order->items()->get()->map(fn ($item) => [
                'work_order_item_id' => $item->id, 'actual_quantity' => $item->planned_quantity,
            ])->all(),
        ];
    }

    private function shipToKitchen(RawMaterial $material, int $quantity): void
    {
        $this->postJson(route('admin.inventory.locations.stock'), [
            'raw_material_id' => $material->id, 'quantity' => $quantity, 'notes' => 'Stok bahan demo',
        ])->assertRedirect();
        $this->send($material, $quantity, 'warehouse-kitchen');
    }

    private function send(RawMaterial $material, int $quantity, string $route): void
    {
        $requestId = (string) Str::uuid();
        $this->postJson(route('admin.inventory.locations.send'), [
            'raw_material_id' => $material->id, 'quantity' => $quantity, 'request_id' => $requestId, 'route' => $route,
        ])->assertRedirect();
        $transfer = StockTransfer::where('request_id', $requestId)->sole();
        $this->postJson(route('admin.inventory.locations.receive', $transfer), ['quantity' => $quantity])->assertRedirect();
    }

    private function assertBalance(RawMaterial $material, string $code, int $quantity): void
    {
        $location = InventoryLocation::where('shop_id', $material->shop_id)->where('code', $code)->sole();
        $this->assertDatabaseHas('inventory_balances', [
            'raw_material_id' => $material->id, 'inventory_location_id' => $location->id, 'quantity' => $quantity,
        ]);
    }
}
