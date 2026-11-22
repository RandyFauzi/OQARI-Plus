<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\InventoryLocation;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockTransfer;
use App\Models\Table;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_enters_business_overview_and_staff_stays_in_assigned_branch(): void
    {
        [$owner, $a, $b] = $this->business();
        $manager = $this->staff($b, 'manager');
        $this->order($a, 'Order Outlet A');
        $this->order($b, 'Order Outlet B');

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertRedirect(route('admin.owner.dashboard'));
        $this->get(route('admin.branches.index'))->assertOk()->assertSee($a->name)->assertSee($b->name);
        $this->get($this->url($a, 'dashboard'))->assertOk()->assertSee($a->name);
        $this->get($this->url($b, 'dashboard'))->assertOk()->assertSee($b->name)->assertSee($this->url($b, 'pos'));
        $this->assertDatabaseMissing('branch_memberships', ['user_id' => $owner->id]);

        $this->actingAs($manager)->getJson($this->url($a, 'api/orders/live'))->assertNotFound();
        $this->getJson($this->url($b, 'api/orders/live'))->assertOk()
            ->assertSee('Order Outlet B')->assertDontSee('Order Outlet A');
        $this->get(route('admin.branches.index'))->assertOk()->assertSee($b->name)->assertDontSee($a->name);
        $this->postJson($this->url($b, 'api/inventory/material'), [])->assertForbidden();
        $this->get($this->url($b, 'central-kitchen'))->assertForbidden();
        $this->postJson($this->url($b, 'api/table'), ['name' => 'Meja B'])->assertOk();
        $this->assertDatabaseHas('tables', ['branch_id' => $b->id, 'name' => 'Meja B']);
        $this->actingAs($owner)->delete(route('admin.branches.unassign', [$b, $manager->id]))->assertRedirect();
        $this->actingAs($manager)->get($this->url($b, 'dashboard'))->assertNotFound();
    }

    public function test_only_destination_branch_can_receive_a_transfer(): void
    {
        [$owner, $a, $b] = $this->business();
        $managerA = $this->staff($a, 'manager');
        $managerB = $this->staff($b, 'manager');
        $material = RawMaterial::create(['shop_id' => $owner->shop_id, 'name' => 'Saus', 'unit' => 'ml', 'current_stock' => 0, 'cost_per_unit' => 1000]);
        $inventory = app(InventoryService::class);
        $warehouse = $inventory->location($owner->shop_id, InventoryLocation::Warehouse);
        $outlet = $inventory->location($owner->shop_id, InventoryLocation::Outlet, $b->id);
        $inventory->move($material, $warehouse, 50, 'in', 'Test', null, 'Test', null);

        $this->actingAs($owner)->post($this->url($b, 'inventory/locations/transfers'), [
            'raw_material_id' => $material->id, 'quantity' => 20, 'request_id' => (string) Str::uuid(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $transfer = StockTransfer::sole();
        $this->assertEquals($outlet->id, $transfer->to_location_id);
        $receive = 'inventory/locations/transfers/'.$transfer->id.'/receive';
        $this->actingAs($managerA)->post($this->url($a, $receive), ['quantity' => 18])->assertNotFound();
        $this->actingAs($managerB)->post($this->url($b, $receive), ['quantity' => 18])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(30, $inventory->quantity($material, $warehouse));
        $this->assertEquals(18, $inventory->quantity($material, $outlet));
        $this->assertEquals(18, $transfer->fresh()->received_quantity);
    }

    public function test_pos_display_is_isolated_by_terminal_and_branch(): void
    {
        [$owner, $a, $b] = $this->business();
        $staff = $this->staff($b, 'cashier');
        $first = $this->register($b, 'Kasir 1');
        $second = $this->register($b, 'Kasir 2');
        $other = $this->register($a, 'Kasir A');
        $sync = $this->url($b, 'pos/customer-display/sync');

        $this->actingAs($staff)->postJson($sync.'?terminal='.$first->id, [
            'status' => 'ordering', 'subtotal' => 16000, 'grand_total' => 16000,
            'items' => [['name' => 'Jawara', 'qty' => 1, 'price' => 16000, 'subtotal' => 16000]],
        ])->assertOk();
        $this->getJson($sync.'?terminal='.$first->id)->assertOk()->assertJsonPath('state.items.0.name', 'Jawara');
        $this->getJson($sync.'?terminal='.$second->id)->assertOk()->assertJsonPath('state', null);
        $this->getJson($sync.'?terminal='.$other->id)->assertNotFound();
        $this->postJson('/cabang/'.$b->slug.'/shift/open', ['opening_cash' => 0, 'register_id' => $other->id])->assertNotFound();
        $this->postJson('/cabang/'.$b->slug.'/shift/open', ['opening_cash' => 0, 'register_id' => $second->id])
            ->assertOk()->assertJsonPath('session.cash_register_id', $second->id);
    }

    public function test_pos_and_qr_only_deduct_stock_from_the_order_branch(): void
    {
        [$owner, $a, $b] = $this->business();
        $cashier = $this->staff($b, 'cashier');
        $register = $this->register($b, 'Kasir 1');
        CashRegisterSession::create([
            'cash_register_id' => $register->id, 'user_id' => $cashier->id, 'opened_at' => now(),
            'opening_cash' => 0, 'status' => 'OPEN',
        ]);
        $product = Product::factory()->create(['shop_id' => $owner->shop_id, 'name' => 'Jawara', 'price' => 16000, 'is_sold_out' => false]);
        $material = RawMaterial::create(['shop_id' => $owner->shop_id, 'name' => 'Patty', 'unit' => 'pcs', 'current_stock' => 20, 'cost_per_unit' => 1000]);
        $product->recipes()->create(['raw_material_id' => $material->id, 'amount' => 1]);
        $group = ModifierGroup::create(['shop_id' => $owner->shop_id, 'name' => 'Tambahan', 'selection_type' => 'MULTIPLE', 'is_active' => true]);
        $product->modifierGroups()->attach($group);
        $daging = $group->modifiers()->create(['name' => 'Daging', 'price_adjustment' => 10000, 'is_active' => true]);
        $daging->ingredients()->attach($material, ['amount' => 1]);
        $inventory = app(InventoryService::class);
        $locationA = $inventory->location($owner->shop_id, InventoryLocation::Outlet, $a->id);
        $locationB = $inventory->location($owner->shop_id, InventoryLocation::Outlet, $b->id);
        $inventory->move($material, $locationB, 10, 'in', 'Test', null, 'Test', null);

        $checkout = [
            'request_id' => (string) Str::uuid(), 'payment_method' => 'CASH', 'amount_paid' => 26000,
            'items' => [['id' => $product->id, 'qty' => 1, 'modifiers' => [$daging->id]]], 'fulfillment_type' => 'TAKEAWAY',
        ];
        $this->actingAs($cashier)->postJson($this->url($b, 'pos/orders'), $checkout)->assertOk();
        $this->postJson($this->url($b, 'pos/orders'), $checkout)->assertOk();
        $this->assertEquals(8, $inventory->quantity($material, $locationB));
        $this->assertEquals(20, $inventory->quantity($material, $locationA));

        $table = Table::create(['shop_id' => $owner->shop_id, 'branch_id' => $b->id, 'name' => 'Meja QR']);
        auth()->forgetGuards();
        $qr = [
            ...$checkout, 'request_id' => (string) Str::uuid(),
            'fulfillment_type' => 'DINE_IN', 'table_token' => $table->public_token, 'customer_name' => 'Pelanggan',
        ];
        $response = $this->postJson(route('shop.order.submit', $owner->shop->slug), $qr)->assertOk();
        $this->postJson(route('shop.order.submit', $owner->shop->slug), $qr)->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $response->json('order.id'), 'branch_id' => $b->id, 'table_id' => $table->id]);
        $this->assertEquals(6, $inventory->quantity($material, $locationB));
        $this->assertEquals(20, $inventory->quantity($material, $locationA));
    }

    private function business(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $manager = app(ManageBranches::class);

        return [$owner, $manager->enable($shop, 'Outlet A'), $manager->create($shop, 'Outlet B')];
    }

    private function staff(Branch $branch, string $role): User
    {
        $user = User::factory()->create(['shop_id' => $branch->shop_id, 'role' => $role]);
        app(ManageBranches::class)->assign($branch, $user, $role);

        return $user;
    }

    private function url(Branch $branch, string $path): string
    {
        return '/cabang/'.$branch->slug.'/admin/'.$path;
    }

    private function order(Branch $branch, string $name): void
    {
        app(BranchContext::class)->select($branch);
        Order::create(['shop_id' => $branch->shop_id, 'customer_name' => $name,
            'payment_method' => 'CASH', 'order_status' => 'CONFIRMED', 'payment_status' => 'PAID',
            'subtotal' => 16000, 'grand_total' => 16000,
        ]);
        app(BranchContext::class)->clear();
    }

    private function register(Branch $branch, string $name): CashRegister
    {
        return CashRegister::create(['shop_id' => $branch->shop_id, 'branch_id' => $branch->id, 'name' => $name, 'is_active' => true]);
    }
}
