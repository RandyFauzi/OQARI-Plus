<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Events\OrderCreated;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaticQrisTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_owner_can_upload_replace_or_disable_the_outlet_qris(): void
    {
        config(['filesystems.media_disk' => 'public']);
        $disk = Storage::fake('public');
        $owner = User::factory()->create(['shop_id' => Shop::factory(), 'role' => 'owner']);
        $outsider = User::factory()->create(['shop_id' => Shop::factory(), 'role' => 'owner']);
        $staff = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'cashier']);
        $branch = app(ManageBranches::class)->enable($owner->shop);
        $otherBranch = app(ManageBranches::class)->create($owner->shop, 'Outlet Selatan');
        $url = route('admin.branches.qris.update', $branch);

        $this->putJson($url)->assertUnauthorized();
        $this->actingAs($outsider)->putJson($url)->assertNotFound();
        $this->actingAs($staff)->putJson($url)->assertForbidden();
        $this->actingAs($owner)->putJson($url, ['static_qris' => UploadedFile::fake()->create('qris.svg', 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('static_qris');

        $this->put($url, ['static_qris' => UploadedFile::fake()->image('qris.png', 420, 420)])->assertRedirect();
        $firstPath = $branch->fresh()->static_qris_path;
        $disk->assertExists($firstPath);
        $this->assertNull($otherBranch->fresh()->static_qris_path);

        $this->put($url, ['static_qris' => UploadedFile::fake()->image('pengganti.jpg', 480, 480)])->assertRedirect();
        $secondPath = $branch->fresh()->static_qris_path;
        $this->assertNotSame($firstPath, $secondPath);
        $disk->assertMissing($firstPath);
        $disk->assertExists($secondPath);

        $this->delete(route('admin.branches.qris.destroy', $branch))->assertRedirect();
        $this->assertNull($branch->fresh()->static_qris_path);
        $disk->assertMissing($secondPath);
    }

    public function test_storefront_uses_the_outlets_qris_and_only_releases_the_order_after_confirmation(): void
    {
        config(['filesystems.media_disk' => 'public']);
        $disk = Storage::fake('public');
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $branches = app(ManageBranches::class);
        $branch = $branches->enable($shop, 'Outlet Utama');
        $otherBranch = $branches->create($shop, 'Outlet Selatan');
        $product = Product::factory()->create(['shop_id' => $shop->id, 'price' => 23500, 'is_sold_out' => false]);
        $site = ['slug' => $shop->slug, 'branchSlug' => $branch->slug];
        $checkout = route('shop.branch.order.submit', $site);
        $payload = [
            'request_id' => (string) Str::uuid(),
            'customer_name' => 'Nadia',
            'fulfillment_type' => 'TAKEAWAY',
            'payment_method' => 'qris',
            'items' => [['id' => $product->id, 'qty' => 2]],
        ];
        $this->postJson($checkout, $payload)->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->postJson($checkout, [...$payload, 'payment_method' => 'QRIS_STATIC'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->actingAs($owner)->put(route('admin.branches.qris.update', $branch), [
            'static_qris' => UploadedFile::fake()->image('qris.png', 420, 420),
        ])->assertRedirect();
        $path = $branch->fresh()->static_qris_path;
        Event::fake([OrderCreated::class]);

        $response = $this->postJson($checkout, $payload)->assertOk()
            ->assertJsonPath('order.payment_status', 'UNPAID')
            ->assertJsonPath('order.order_status', 'HOLD')
            ->assertJsonPath('order.amount_paid', 0)
            ->assertJsonMissingPath('order.payment_qr_path')
            ->assertJsonPath('order.grand_total', 47000);
        $orderId = $response->json('order.id');
        $trackingUrl = $response->json('tracking_url');
        $this->postJson($checkout, $payload)->assertOk()->assertJsonPath('order.id', $orderId);
        $this->get($trackingUrl)->assertOk()->assertSee('Menunggu pembayaran QRIS')->assertSee($path);
        $this->get(route('shop.branch.tracking', [...$site, 'order' => $orderId]))->assertForbidden();
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'stock_posted_at' => null]);
        $this->assertDatabaseMissing('payments', ['order_id' => $orderId]);
        Event::assertNotDispatched(OrderCreated::class);

        $this->put(route('admin.branches.qris.update', $branch), [
            'static_qris' => UploadedFile::fake()->image('new.png', 420, 420),
        ])->assertRedirect();
        $disk->assertExists($path);
        $this->get($trackingUrl)->assertOk()->assertSee($path)->assertDontSee($branch->fresh()->static_qris_path);

        $branchRoutes = ['operatingBranch' => $branch->slug, 'order' => $orderId];
        $confirm = route('branch.admin.pos.qris.confirm', $branchRoutes);
        $payment = ['reference' => 'QR-123', 'received_amount' => 47000];
        $this->postJson(route('branch.admin.pos.qris.confirm', ['operatingBranch' => $otherBranch->slug, 'order' => $orderId]), $payment)->assertNotFound();
        $this->postJson($confirm, [...$payment, 'received_amount' => 46000])->assertUnprocessable()->assertJsonValidationErrors('received_amount');
        $this->postJson($confirm, $payment)->assertUnprocessable()->assertJsonValidationErrors('amount_paid');
        $this->postJson("/cabang/{$branch->slug}/admin/api/orders/{$orderId}/status", ['status' => 'PREPARING'])->assertUnprocessable();
        $this->postJson(route('branch.admin.pos.orders.recall', $branchRoutes))->assertNotFound();

        $register = CashRegister::where('branch_id', $branch->id)->firstOrFail();
        $session = CashRegisterSession::create([
            'cash_register_id' => $register->id,
            'user_id' => $owner->id,
            'opened_at' => now(),
            'status' => 'OPEN',
            'opening_cash' => 0,
        ]);
        $this->getJson(route('branch.admin.pos.qris.index', ['operatingBranch' => $branch->slug]))
            ->assertJsonPath('0.id', $orderId);
        $this->postJson($confirm, [...$payment, 'reference' => 'qr-123'])->assertOk()->assertJsonPath('payment_status', 'PAID');
        $this->postJson($confirm, $payment)->assertOk();
        $this->get($trackingUrl)->assertOk()->assertSee('Pembayaran dikonfirmasi')->assertDontSee($path);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'order_status' => 'CONFIRMED', 'payment_status' => 'PAID']);
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'amount' => 47000, 'reference_number' => 'QR-123']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(47000, $session->fresh()->total_qris_sales);
        Event::assertDispatchedTimes(OrderCreated::class, 1);

        $secondId = $this->postJson($checkout, [...$payload, 'request_id' => (string) Str::uuid()])->assertOk()->json('order.id');
        $secondConfirm = route('branch.admin.pos.qris.confirm', ['operatingBranch' => $branch->slug, 'order' => $secondId]);
        $this->postJson($secondConfirm, $payment)->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertDatabaseHas('orders', ['id' => $secondId, 'payment_status' => 'UNPAID']);
    }
}
