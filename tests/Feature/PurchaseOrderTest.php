<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\InventoryLocation;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_po_posts_only_accepted_goods_to_warehouse_and_closes_after_replacement(): void
    {
        [$owner, $patty] = $this->buyer();
        $buns = RawMaterial::create([
            'shop_id' => $owner->shop_id, 'name' => 'Roti Wijen', 'unit' => 'pcs',
            'current_stock' => 0, 'cost_per_unit' => 2200,
        ]);
        $this->actingAs($owner)->postJson(route('admin.purchases.suppliers.store'), [
            'name' => 'Supplier Burgerkat Demo', 'contact' => 'Kontak demo',
        ])->assertRedirect(route('admin.purchases.index'));
        $supplier = Supplier::sole();
        $this->get(route('admin.purchases.index'))->assertOk()->assertSee('Supplier Burgerkat Demo')->assertSee('Buat PO');

        $purchase = [
            'supplier_id' => $supplier->id, 'request_id' => (string) Str::uuid(),
            'items' => [
                ['raw_material_id' => $patty->id, 'quantity' => 100, 'unit_cost' => 5750],
                ['raw_material_id' => $buns->id, 'quantity' => 12, 'unit_cost' => 2200],
            ],
        ];
        $this->postJson(route('admin.purchases.store'), $purchase)->assertRedirect();
        $order = PurchaseOrder::sole();
        $this->postJson(route('admin.purchases.store'), $purchase)->assertRedirect(route('admin.purchases.show', $order));
        $this->get(route('admin.purchases.show', $order))->assertOk()->assertSee('Menunggu persetujuan')->assertSee('Setujui PO');

        $items = $order->items()->get()->keyBy('raw_material_id');
        $receipt = [
            'request_id' => (string) Str::uuid(), 'delivery_reference' => 'SJ-BKT-01',
            'items' => [
                $this->line($items[$patty->id]->id, 80, 5, 'Segel rusak'),
                $this->line($items[$buns->id]->id, 12),
            ],
        ];
        $receiveUrl = route('admin.purchases.receive', $order);
        $this->postJson($receiveUrl, $receipt)->assertUnprocessable();
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->postJson(route('admin.purchases.approve', $order))->assertRedirect();
        $this->postJson($receiveUrl, $receipt)->assertRedirect();
        $this->postJson($receiveUrl, $receipt)->assertRedirect();
        $warehouse = InventoryLocation::where('shop_id', $owner->shop_id)->where('code', InventoryLocation::Warehouse)->sole();
        $posted = GoodsReceipt::sole();

        $this->assertSame(PurchaseOrder::Partial, $order->fresh()->status);
        $this->assertSame(20.0, $items[$patty->id]->fresh()->remainingQuantity());
        $this->assertSame('11.000000', $patty->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_balances', ['inventory_location_id' => $warehouse->id, 'raw_material_id' => $patty->id, 'quantity' => 80]);
        $this->assertDatabaseHas('goods_receipt_items', [
            'goods_receipt_id' => $posted->id, 'purchase_order_item_id' => $items[$patty->id]->id,
            'accepted_quantity' => 80, 'rejected_quantity' => 5, 'rejection_reason' => 'Segel rusak',
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'inventory_location_id' => $warehouse->id, 'raw_material_id' => $patty->id, 'amount' => 80,
            'reference_type' => 'GoodsReceipt', 'reference_id' => $posted->id, 'unit_cost' => 5750,
        ]);
        $this->get(route('admin.purchases.show', $order))->assertOk()->assertSee('Diterima sebagian')->assertSee('Segel rusak');

        $receipt['request_id'] = (string) Str::uuid();
        $this->postJson($receiveUrl, $receipt)->assertUnprocessable()->assertJsonValidationErrors('delivery_reference');
        $last = [
            'request_id' => (string) Str::uuid(), 'delivery_reference' => 'SJ-BKT-02',
            'items' => [$this->line($items[$patty->id]->id, 20)],
        ];
        $this->postJson($receiveUrl, $last)->assertRedirect();
        $this->postJson($receiveUrl, $last)->assertRedirect();
        $this->assertSame(PurchaseOrder::Received, $order->fresh()->status);
        $this->assertDatabaseHas('inventory_balances', ['inventory_location_id' => $warehouse->id, 'raw_material_id' => $patty->id, 'quantity' => 100]);
        $this->assertDatabaseCount('goods_receipts', 2);
        $this->assertDatabaseCount('stock_mutations', 3);
    }

    public function test_receipt_validation_leaves_stock_intact_for_rejected_only_and_overdelivery(): void
    {
        [$owner, $patty] = $this->buyer();
        $this->actingAs($owner);
        $order = $this->createPo($owner, $patty);
        $this->postJson(route('admin.purchases.approve', $order))->assertRedirect();
        $item = $order->items()->sole();
        $url = route('admin.purchases.receive', $order);
        $receipt = ['request_id' => (string) Str::uuid(), 'delivery_reference' => 'SJ-01'];
        $this->postJson($url, $receipt + ['items' => [$this->line($item->id, 0, 0)]])->assertUnprocessable();
        $this->postJson($url, $receipt + ['items' => [$this->line($item->id, 0, 2)]])->assertUnprocessable();
        $this->postJson($url, $receipt + ['items' => [$this->line($item->id, 10, 1, 'Rusak')]])->assertUnprocessable();
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->postJson($url, $receipt + ['items' => [$this->line($item->id, 0, 2, 'Rusak')]])->assertRedirect();
        $this->postJson($url, $receipt + ['items' => [$this->line($item->id, 2)]])->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('stock_mutations', 0);
        $this->assertEquals(10, $item->fresh()->remainingQuantity());
        $this->assertSame(PurchaseOrder::Approved, $order->fresh()->status);
    }

    public function test_role_and_tenant_boundaries_for_approval_and_receipt(): void
    {
        [$owner, $patty] = $this->buyer();
        [$other, $otherPatty] = $this->buyer();
        $clerk = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'crew']);
        $clerk->forceFill(['permissions' => ['view-purchases', 'manage-purchases']])->save();
        $this->actingAs($owner);
        $order = $this->createPo($owner, $patty);
        $item = $order->items()->sole();

        $this->actingAs($clerk)->get(route('admin.purchases.show', $order))->assertOk()->assertDontSee('Setujui PO');
        $this->postJson(route('admin.purchases.approve', $order))->assertForbidden();
        $payload = ['request_id' => (string) Str::uuid(), 'delivery_reference' => 'SJ-X', 'items' => [$this->line($item->id, 1)]];
        $this->postJson(route('admin.purchases.receive', $order), $payload)->assertForbidden();
        $this->actingAs($other)->get(route('admin.purchases.show', $order))->assertNotFound();
        $this->postJson(route('admin.purchases.approve', $order))->assertNotFound();
        $this->postJson(route('admin.purchases.receive', $order), $payload)->assertNotFound();
        $otherOrder = $this->createPo($other, $otherPatty);
        $this->postJson(route('admin.purchases.approve', $otherOrder))->assertRedirect();
        $this->postJson(route('admin.purchases.receive', $otherOrder), $payload)->assertNotFound();

        $supplier = $otherOrder->supplier;
        $this->actingAs($owner)->postJson(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id, 'request_id' => (string) Str::uuid(),
            'items' => [['raw_material_id' => $patty->id, 'quantity' => 1, 'unit_cost' => 1]],
        ])->assertNotFound();
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    /** @return array{User, RawMaterial} */
    private function buyer(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $patty = RawMaterial::create([
            'shop_id' => $shop->id, 'name' => 'Patty Sapi Tebal', 'unit' => 'pcs',
            'current_stock' => 11, 'cost_per_unit' => 5600,
        ]);

        return [$owner, $patty];
    }

    private function createPo(User $owner, RawMaterial $material): PurchaseOrder
    {
        $supplier = Supplier::factory()->create(['shop_id' => $owner->shop_id, 'name' => 'Supplier Demo']);
        $this->postJson(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id, 'request_id' => (string) Str::uuid(),
            'items' => [['raw_material_id' => $material->id, 'quantity' => 10, 'unit_cost' => 5600]],
        ])->assertRedirect();

        return $supplier->purchaseOrders()->sole();
    }

    /** @return array<string, int|string|null> */
    private function line(int $item, int $accepted, int $rejected = 0, ?string $reason = null): array
    {
        return ['purchase_order_item_id' => $item, 'accepted_quantity' => $accepted, 'rejected_quantity' => $rejected, 'rejection_reason' => $reason];
    }
}
