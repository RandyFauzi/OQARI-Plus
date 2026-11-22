<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Events\OrderCreated;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\BranchMenuResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BranchPublicUrlAndRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_public_urls_persist_context_across_menu_cart_and_tracking(): void
    {
        $shop = Shop::factory()->create(['name' => 'Kopi Pusat', 'onboarding_status' => 'completed']);
        $branches = app(ManageBranches::class);
        $branchA = $branches->enable($shop, 'Outlet Pusat');
        $branchB = $branches->create($shop, 'Outlet Selatan');

        $branchB->update([
            'settings' => [
                'name' => 'Kopi Selatan',
                'tagline' => 'Cabang Selatan',
            ],
        ]);

        Product::create([
            'shop_id' => $shop->id,
            'name' => 'Americano',
            'price' => 20000,
            'cogs' => 7000,
        ]);

        // Visiting /{slug}/{branchB} renders Branch B storefront and sets session branch context
        $this->get(route('shop.branch.menu', ['slug' => $shop->slug, 'branchSlug' => $branchB->slug]))
            ->assertOk()
            ->assertSee('Kopi Selatan')
            ->assertSee(route('shop.branch.cart', ['slug' => $shop->slug, 'branchSlug' => $branchB->slug]))
            ->assertSee(route('shop.branch.order.submit', ['slug' => $shop->slug, 'branchSlug' => $branchB->slug]));

        // Branch-scoped cart & tracking render Branch B storefront
        $this->get(route('shop.branch.cart', ['slug' => $shop->slug, 'branchSlug' => $branchB->slug]))
            ->assertOk()
            ->assertSee('Kopi Selatan');

        $this->get(route('shop.branch.tracking', ['slug' => $shop->slug, 'branchSlug' => $branchB->slug]))
            ->assertOk()
            ->assertSee('Kopi Selatan');

        // Legacy /cart and /tracking also inherit Branch B from session
        $this->get(route('shop.cart', ['slug' => $shop->slug]))
            ->assertOk()
            ->assertSee('Kopi Selatan');

        $this->get(route('shop.tracking', ['slug' => $shop->slug]))
            ->assertOk()
            ->assertSee('Kopi Selatan');

        // Unknown branch slug returns 404
        $this->get("/{$shop->slug}/cabang-tidak-ada")->assertNotFound();
    }

    public function test_takeaway_order_on_branch_url_assigns_correct_branch_and_broadcasts_to_branch_channel(): void
    {
        Event::fake([OrderCreated::class]);

        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $branches = app(ManageBranches::class);
        $branchA = $branches->enable($shop, 'Outlet Pusat');
        $branchB = $branches->create($shop, 'Outlet Selatan');

        $product = Product::create([
            'shop_id' => $shop->id,
            'name' => 'Es Kopi Susu',
            'price' => 18000,
            'cogs' => 6000,
        ]);

        // Override price on Branch B to 22000
        $this->actingAs($owner)
            ->postJson("/cabang/{$branchB->slug}/admin/api/menu/{$product->id}/branch-override", [
                'is_available' => true,
                'price_override' => 22000,
            ])
            ->assertOk();

        // Guest places TAKEAWAY order on Branch B's public URL
        $response = $this->postJson(route('shop.branch.order.submit', [
            'slug' => $shop->slug,
            'branchSlug' => $branchB->slug,
        ]), [
            'customer_name' => 'Budi',
            'fulfillment_type' => 'TAKEAWAY',
            'payment_method' => 'cash',
            'items' => [
                ['id' => $product->id, 'qty' => 2],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('order.branch_id', $branchB->id)
            ->assertJsonPath('order.grand_total', 44000);

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($shop, $branchB): bool {
            $channels = array_map(fn ($ch) => $ch->name, $event->broadcastOn());
            $payload = $event->broadcastWith();

            return $channels === ["private-shop.{$shop->id}.branch.{$branchB->id}.orders"]
                && (int) ($payload['branch_id'] ?? 0) === $branchB->id;
        });

        // Verify BranchMenuResolver still intact on Branch A
        $resolvedA = app(BranchMenuResolver::class)->applyToProduct($product->fresh(), $branchA);
        $this->assertEquals(18000, (float) $resolvedA->price);
    }
}
