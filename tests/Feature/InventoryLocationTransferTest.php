<?php

namespace Tests\Feature;

use App\Actions\Modifiers\SaveModifierGroupAction;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryLocationTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_jawara_with_daging_consumes_two_outlet_patties_once_after_partial_transfer_and_payment(): void
    {
        [$owner, $cashier, $patty, $jawara, $daging] = $this->burgerkat();
        $this->actingAs($owner)->get(route('admin.inventory.locations.index'))->assertOk()->assertSee('Stok per Lokasi');
        $this->postJson(route('admin.inventory.locations.stock'), [
            'raw_material_id' => $patty->id, 'quantity' => 100, 'notes' => 'Saldo awal demo',
        ])->assertRedirect();

        $shipment = ['raw_material_id' => $patty->id, 'quantity' => 30, 'request_id' => (string) Str::uuid()];
        $this->postJson(route('admin.inventory.locations.send'), $shipment)->assertRedirect();
        $this->postJson(route('admin.inventory.locations.send'), $shipment)->assertRedirect();
        $transfer = StockTransfer::sole();
        $this->assertBalance($patty, $transfer->from_location_id, 70);
        $this->assertSame('0.000000', $patty->fresh()->current_stock);

        $receive = route('admin.inventory.locations.receive', $transfer);
        $this->postJson($receive, ['quantity' => 31])->assertUnprocessable();
        $this->postJson($receive, ['quantity' => 28])->assertRedirect();
        $this->postJson($receive, ['quantity' => 28])->assertRedirect();
        $this->assertBalance($patty, $transfer->to_location_id, 28);
        $this->assertSame(2.0, $transfer->fresh()->remainingQuantity());
        $this->assertDatabaseCount('stock_transfers', 1);

        $this->app['auth']->forgetGuards();
        $qr = route('shop.order.submit', $owner->shop->slug);
        $items = [['id' => $jawara->id, 'qty' => 1, 'modifiers' => [$daging->id]]];
        $qrPayload = [
            'request_id' => (string) Str::uuid(),
            'customer_name' => 'Demo Burgerkat',
            'fulfillment_type' => 'TAKEAWAY',
            'payment_method' => 'CASH',
            'items' => $items,
        ];
        $orderId = $this->postJson($qr, $qrPayload)->assertOk()->assertJsonPath('order.grand_total', 26000)->json('order.id');
        $this->postJson($qr, $qrPayload)->assertOk()->assertJsonPath('order.id', $orderId);
        $this->assertBalance($patty, $transfer->to_location_id, 26);
        $this->assertBalance($patty, $transfer->from_location_id, 70);

        $this->actingAs($cashier)->postJson(route('shift.open'), ['opening_cash' => 0])->assertOk();
        $payment = [
            'request_id' => (string) Str::uuid(), 'recalled_order_id' => $orderId,
            'payment_method' => 'CASH', 'amount_paid' => 26000, 'items' => $items,
        ];
        $this->postJson(route('admin.pos.orders.submit'), $payment)->assertOk()->assertJsonPath('order_id', $orderId);
        $this->postJson(route('admin.pos.orders.submit'), $payment)->assertOk()->assertJsonPath('order_id', $orderId);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'PAID']);
        $this->assertDatabaseHas('stock_mutations', [
            'reference_type' => 'Order', 'reference_id' => $orderId, 'raw_material_id' => $patty->id,
            'inventory_location_id' => $transfer->to_location_id, 'amount' => -2, 'balance_after' => 26,
        ]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, $patty->mutations()->where('type', 'out_order')->count());
        $this->assertBalance($patty, $transfer->to_location_id, 26);
    }

    public function test_pos_hold_keeps_outlet_stock_and_checkout_deducts_recipe_and_addon_once(): void
    {
        [, $cashier, $patty, $jawara, $daging] = $this->burgerkat(28);
        $this->actingAs($cashier)->postJson(route('shift.open'), ['opening_cash' => 0])->assertOk();
        $items = [['id' => $jawara->id, 'qty' => 1, 'modifiers' => [$daging->id]]];
        $orderId = $this->postJson(route('admin.pos.orders.hold'), ['items' => $items])->assertOk()->json('order_id');
        $this->assertSame('28.000000', $patty->fresh()->current_stock);

        $payment = [
            'request_id' => (string) Str::uuid(), 'recalled_order_id' => $orderId,
            'payment_method' => 'CASH', 'amount_paid' => 26000, 'items' => $items,
        ];
        $this->postJson(route('admin.pos.orders.submit'), $payment)->assertOk()->assertJsonPath('order_id', $orderId);
        $this->postJson(route('admin.pos.orders.submit'), $payment)->assertOk();
        $this->assertSame('26.000000', $patty->fresh()->current_stock);
        $this->assertSame(1, $patty->mutations()->where('type', 'out_order')->count());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_insufficient_outlet_stock_rolls_back_pos_and_qr_orders(): void
    {
        [$owner, $cashier, $patty, $jawara, $daging] = $this->burgerkat(1);
        $items = [['id' => $jawara->id, 'qty' => 1, 'modifiers' => [$daging->id]]];

        $this->postJson(route('shop.order.submit', $owner->shop->slug), [
            'request_id' => (string) Str::uuid(), 'customer_name' => 'Demo', 'payment_method' => 'CASH',
            'fulfillment_type' => 'TAKEAWAY', 'items' => $items,
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->actingAs($cashier)->postJson(route('shift.open'), ['opening_cash' => 0])->assertOk();
        $this->postJson(route('admin.pos.orders.submit'), [
            'request_id' => (string) Str::uuid(), 'payment_method' => 'CASH', 'amount_paid' => 26000, 'items' => $items,
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('1.000000', $patty->fresh()->current_stock);
    }

    public function test_transfer_rejects_insufficient_warehouse_stock_and_other_tenant_access(): void
    {
        [$owner, $cashier, $patty] = $this->burgerkat();
        $send = route('admin.inventory.locations.send');
        $payload = ['raw_material_id' => $patty->id, 'quantity' => 1, 'request_id' => (string) Str::uuid()];
        $this->postJson($send, $payload)->assertUnauthorized();
        $this->actingAs($cashier)->postJson($send, $payload)->assertForbidden();
        $other = User::factory()->create(['shop_id' => Shop::factory(), 'role' => 'owner']);
        $this->actingAs($other)->postJson($send, $payload)->assertNotFound();
        $this->actingAs($owner)->postJson($send, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('stock_transfers', 0);

        $this->postJson(route('admin.inventory.locations.stock'), [
            'raw_material_id' => $patty->id, 'quantity' => 1, 'notes' => 'Saldo awal',
        ])->assertRedirect();
        $this->postJson($send, $payload)->assertRedirect();
        $receive = route('admin.inventory.locations.receive', StockTransfer::sole());
        $this->actingAs($other)->postJson($receive, ['quantity' => 1])->assertNotFound();
        $this->assertSame('0.000000', $patty->fresh()->current_stock);
    }

    public function test_confirmed_batch_retry_and_cancellation_keep_inventory_consistent(): void
    {
        [$owner, , $patty, $jawara, $daging] = $this->burgerkat(28);
        $items = [['id' => $jawara->id, 'qty' => 1, 'modifiers' => [$daging->id]]];
        $orderId = $this->postJson(route('shop.order.submit', $owner->shop->slug), [
            'request_id' => (string) Str::uuid(), 'customer_name' => 'Demo', 'payment_method' => 'CASH',
            'fulfillment_type' => 'TAKEAWAY', 'items' => $items,
        ])->assertOk()->json('order.id');

        $payload = [
            'recalled_order_id' => $orderId, 'append_batch' => true, 'batch_request_id' => (string) Str::uuid(),
            'items' => $items,
        ];
        $this->actingAs($owner)->postJson(route('admin.pos.orders.hold'), $payload)->assertOk()->assertJsonPath('batch_no', 2);
        $this->postJson(route('admin.pos.orders.hold'), $payload)->assertOk()->assertJsonPath('batch_no', 2);
        $this->assertSame('24.000000', $patty->fresh()->current_stock);

        $path = '/admin/api/orders/'.$orderId.'/status';
        $this->postJson($path, ['status' => 'CANCELLED'])->assertOk();
        $this->postJson($path, ['status' => 'CANCELLED'])->assertOk();
        $this->postJson($path, ['status' => 'CONFIRMED'])->assertUnprocessable();
        $this->assertSame('28.000000', $patty->fresh()->current_stock);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame(2, $patty->mutations()->where('reference_type', 'OrderCancellation')->count());
    }

    private function assertBalance(RawMaterial $material, int $locationId, int $quantity): void
    {
        $this->assertDatabaseHas('inventory_balances', [
            'raw_material_id' => $material->id, 'inventory_location_id' => $locationId, 'quantity' => $quantity,
        ]);
    }

    /** @return array{User, User, RawMaterial, Product, Modifier} */
    private function burgerkat(int $outletStock = 0): array
    {
        $shop = Shop::factory()->create(['name' => 'Burgerkat Demo', 'slug' => 'burgerkat-demo', 'onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $cashier = User::factory()->create(['shop_id' => $shop->id, 'role' => 'cashier']);
        $patty = RawMaterial::create([
            'shop_id' => $shop->id, 'name' => 'Patty Sapi Tebal', 'unit' => 'pcs',
            'current_stock' => $outletStock, 'cost_per_unit' => 5600,
        ]);
        $jawara = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Jawara', 'price' => 16000, 'is_sold_out' => false]);
        $jawara->recipes()->create(['raw_material_id' => $patty->id, 'amount' => 1]);
        $group = app(SaveModifierGroupAction::class)->execute($shop, [
            'name' => 'Tambahan', 'product_ids' => [$jawara->id],
            'items' => [['name' => 'Daging', 'price_adjustment' => 10000, 'cogs_adjustment' => 5600]],
        ]);
        $daging = $group->modifiers->sole();
        $daging->ingredients()->attach($patty, ['amount' => 1]);

        return [$owner, $cashier, $patty, $jawara, $daging];
    }
}
