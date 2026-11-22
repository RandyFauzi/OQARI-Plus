<?php

namespace Tests\Feature;

use App\Models\CashDrawerTransaction;
use App\Models\CashRegisterSession;
use App\Models\InventoryLocation;
use App\Models\OutletStockRequest;
use App\Models\PettyPurchase;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_burgerkat_request_partial_receipt_and_weekly_count_are_auditable(): void
    {
        [$owner, $sauce] = $this->shop();
        $this->actingAs($owner)->get(route('admin.controls.index'))->assertOk()->assertSee('Ajukan barang untuk outlet');
        $this->postJson(route('admin.inventory.locations.stock'), ['raw_material_id' => $sauce->id, 'quantity' => 1000, 'notes' => 'Saldo demo'])->assertRedirect();
        $requestPayload = ['request_id' => (string) Str::uuid(), 'raw_material_id' => $sauce->id, 'source' => 'warehouse', 'quantity' => 500];
        $this->postJson(route('admin.controls.requests.store'), $requestPayload)->assertRedirect();
        $this->postJson(route('admin.controls.requests.store'), $requestPayload)->assertRedirect();
        $request = OutletStockRequest::sole();
        $dispatch = ['request_id' => (string) Str::uuid()];
        $this->postJson(route('admin.controls.requests.dispatch', $request), $dispatch)->assertUnprocessable();
        $this->postJson(route('admin.controls.requests.review', $request), ['decision' => 'approve', 'approved_quantity' => 501])->assertUnprocessable();
        $this->postJson(route('admin.controls.requests.review', $request), ['decision' => 'approve', 'approved_quantity' => 500])->assertRedirect();
        $this->postJson(route('admin.controls.requests.dispatch', $request), $dispatch)->assertRedirect();
        $this->postJson(route('admin.controls.requests.dispatch', $request), $dispatch)->assertRedirect();
        $transfer = StockTransfer::sole();
        $this->postJson(route('admin.inventory.locations.receive', $transfer), ['quantity' => 480])->assertRedirect();
        $this->assertSame($transfer->id, $request->fresh()->stock_transfer_id);
        $this->assertSame(20.0, $transfer->fresh()->remainingQuantity());
        $this->assertSame('3480.000000', $sauce->fresh()->current_stock);

        $countUrl = route('admin.controls.index', ['tab' => 'counts']);
        $this->get($countUrl)->assertOk()->assertSee('Catat hitung fisik');
        $this->postJson(route('admin.controls.counts.store'), $this->countPayload($sauce, 3460, 'WEEKLY'))->assertRedirect();
        $count = StockCount::sole();
        $this->assertSame(1, $count->period_start->dayOfWeekIso);
        $this->assertSame(7, $count->period_end->dayOfWeekIso);
        $this->assertSame('3480.000000', $sauce->fresh()->current_stock);
        $this->postJson(route('admin.controls.counts.review', $count), ['decision' => 'approve'])->assertRedirect();
        $this->postJson(route('admin.controls.counts.review', $count), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('3460.000000', $sauce->fresh()->current_stock);
        $this->assertDatabaseHas('stock_mutations', ['reference_type' => 'StockCount', 'reference_id' => $count->id, 'amount' => -20]);
        $this->get(route('admin.controls.variance'))->assertOk()->assertSee('3.480')->assertSee('3.460')->assertSee('-20');
        $this->get(route('admin.controls.variance', ['type' => 'transfers']))->assertOk()->assertSee('Belum diterima 20');
        $this->get(route('admin.controls.variance', ['type' => 'production']))->assertOk()->assertSee('Tidak ada selisih');
        $this->get(route('admin.controls.variance', ['through' => now()->subDays(15)->toDateString()]))->assertOk()->assertDontSee('Saus Burgerkat');
    }

    public function test_stock_movement_after_count_requires_recount_even_when_quantity_returns(): void
    {
        [$owner, $sauce] = $this->shop();
        $this->actingAs($owner)->postJson(route('admin.controls.counts.store'), $this->countPayload($sauce, 2999))->assertRedirect();
        $count = StockCount::sole();
        $this->assertSame($count->period_start->toDateString(), $count->period_end->toDateString());
        $this->postJson('/admin/api/inventory/adjust', ['raw_material_id' => $sauce->id, 'type' => 'in', 'amount' => 1])->assertOk();
        $this->postJson('/admin/api/inventory/adjust', ['raw_material_id' => $sauce->id, 'type' => 'waste', 'amount' => 1])->assertOk();
        $url = route('admin.controls.counts.review', $count);
        $this->postJson($url, ['decision' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson($url, ['decision' => 'reject'])->assertUnprocessable();
        $this->postJson($url, ['decision' => 'reject', 'decision_note' => 'Hitung ulang'])->assertRedirect();
        $this->assertSame('REJECTED', $count->fresh()->status);
        $this->assertSame('3000.000000', $sauce->fresh()->current_stock);
    }

    public function test_petty_purchase_requires_proof_posts_cash_once_and_receives_inventory_separately(): void
    {
        Storage::fake('local');
        [$owner, $sauce] = $this->shop();
        $this->actingAs($owner)->postJson(route('shift.open'), ['opening_cash' => 100000])->assertOk();
        $shift = CashRegisterSession::sole();
        $payload = [
            'request_id' => (string) Str::uuid(), 'description' => 'Bahan demo', 'funding' => 'DRAWER',
            'amount' => 24000, 'cash_register_session_id' => $shift->id,
            'raw_material_id' => $sauce->id, 'quantity' => 600,
        ];
        $route = route('admin.controls.purchases.store');
        $this->post($route, $payload, ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('proof');
        $this->post($route, $payload + ['proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')])->assertRedirect();
        $this->post($route, $payload + ['proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')])->assertRedirect();
        $purchase = PettyPurchase::sole();
        Storage::disk('local')->assertExists($purchase->proof_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->get(route('admin.controls.index', ['tab' => 'purchases']))->assertOk()->assertSee('Unduh bukti pembelian');
        $this->get(route('admin.controls.purchases.proof', $purchase))->assertOk()->assertDownload();
        $this->postJson(route('admin.controls.purchases.receive', $purchase))->assertUnprocessable();
        $this->assertDatabaseCount('cash_drawer_transactions', 0);

        $review = route('admin.controls.purchases.review', $purchase);
        $this->postJson($review, ['decision' => 'approve'])->assertRedirect();
        $this->postJson($review, ['decision' => 'approve'])->assertRedirect();
        $cash = CashDrawerTransaction::sole();
        $this->assertSame($cash->id, $purchase->fresh()->cash_drawer_transaction_id);
        $this->assertDatabaseHas('cash_drawer_transactions', ['id' => $cash->id, 'cash_register_session_id' => $shift->id, 'type' => 'CASH_OUT', 'amount' => 24000]);
        $this->assertSame('3000.000000', $sauce->fresh()->current_stock);
        $receive = route('admin.controls.purchases.receive', $purchase);
        $this->postJson($receive)->assertRedirect();
        $this->postJson($receive)->assertRedirect();
        $this->assertSame('3600.000000', $sauce->fresh()->current_stock);
        $this->assertDatabaseHas('stock_mutations', ['reference_type' => 'PettyPurchase', 'reference_id' => $purchase->id, 'amount' => 600, 'unit_cost' => 40]);

        $this->postJson(route('shift.close'), ['actual_cash' => 75000, 'notes' => 'Selisih demo'])->assertOk();
        $this->get(route('admin.controls.variance', ['type' => 'cash']))->assertOk()->assertSee('76.000')->assertSee('75.000')->assertSee('-1.000');
    }

    public function test_permissions_and_tenant_boundaries_protect_reviews_and_evidence(): void
    {
        Storage::fake('local');
        [$owner, $sauce] = $this->shop();
        [$other] = $this->shop();
        $operator = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'crew']);
        $operator->forceFill(['permissions' => ['view-inventory-controls', 'record-inventory-controls']])->save();
        $request = OutletStockRequest::factory()->create(['shop_id' => $owner->shop_id, 'raw_material_id' => $sauce->id]);
        $review = route('admin.controls.requests.review', $request);
        $this->postJson($review)->assertUnauthorized();
        $this->actingAs($operator)->postJson($review, ['decision' => 'approve', 'approved_quantity' => 1])->assertForbidden();
        $this->get(route('admin.controls.variance'))->assertForbidden();
        $this->actingAs($other)->postJson($review, ['decision' => 'approve', 'approved_quantity' => 1])->assertNotFound();
        $this->actingAs($owner)->post(route('admin.controls.purchases.store'), [
            'request_id' => (string) Str::uuid(), 'description' => 'Bukti privat', 'funding' => 'EXTERNAL', 'amount' => 2000,
            'proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $purchase = PettyPurchase::sole();
        $url = route('admin.controls.purchases.proof', $purchase);
        $this->actingAs($operator)->get($url)->assertNotFound();
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($owner)->postJson(route('admin.controls.purchases.review', $purchase), ['decision' => 'approve'])->assertRedirect();
        $this->assertDatabaseCount('cash_drawer_transactions', 0);
    }

    /** @return array{User, RawMaterial} */
    private function shop(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $sauce = RawMaterial::create(['shop_id' => $shop->id, 'name' => 'Saus Burgerkat', 'unit' => 'ml', 'current_stock' => 3000]);

        return [$owner, $sauce];
    }

    private function countPayload(RawMaterial $material, int $quantity, string $frequency = 'DAILY'): array
    {
        return [
            'request_id' => (string) Str::uuid(), 'location' => InventoryLocation::Outlet,
            'frequency' => $frequency, 'count_date' => now(config('attendance.timezone'))->toDateString(),
            'items' => [['raw_material_id' => $material->id, 'counted_quantity' => $quantity]],
        ];
    }
}
