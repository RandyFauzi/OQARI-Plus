<?php

namespace Tests\Feature;

use App\Events\PosCustomerAction;
use App\Events\PosDisplayUpdated;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCustomerDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_screens_require_pos_permission_and_kiosk_loads_its_own_catalog(): void
    {
        $shop = Shop::factory()->create(['name' => 'Burgerkat Demo', 'onboarding_status' => 'completed']);
        $cashier = $this->cashier($shop);
        $staff = User::factory()->create(['shop_id' => $shop->id, 'role' => 'waiter']);
        Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Jawara']);
        Product::factory()->create(['name' => 'Menu Toko Lain']);

        foreach (['display', 'kiosk'] as $screen) {
            $route = route('admin.pos.customer-'.$screen);
            $this->get($route)->assertRedirect();
            $this->actingAs($staff)->get($route)->assertForbidden();
            $this->actingAs($cashier)->get($route)->assertOk()->assertSee('Burgerkat Demo');
            auth()->forgetGuards();
        }

        $this->actingAs($cashier)->get(route('admin.pos.customer-kiosk'))
            ->assertSee('Jawara')->assertDontSee('Menu Toko Lain');
    }

    public function test_display_validates_and_broadcasts_snapshot_on_its_private_channel(): void
    {
        Event::fake([PosDisplayUpdated::class]);
        $cashier = $this->cashier();

        $this->actingAs($cashier)->postJson(route('admin.pos.customer-display.sync'), [
            ...$this->state(), 'status' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->postJson(route('admin.pos.customer-display.sync'), $this->state())
            ->assertOk()->assertJsonPath('success', true);

        Event::assertDispatched(PosDisplayUpdated::class, fn (PosDisplayUpdated $event) => $event->broadcastAs() === 'pos.display.updated'
            && $event->broadcastOn()[0]->name === 'private-shop.'.$cashier->shop_id.'.pos-display'
            && $event->broadcastWith()['items'][0]['name'] === 'Jawara');

        $this->getJson(route('admin.pos.customer-display.snapshot'))->assertOk()
            ->assertJsonPath('state.grand_total', 25000);
        $this->actingAs($this->cashier())->getJson(route('admin.pos.customer-display.snapshot'))->assertOk()
            ->assertJsonPath('state', null);
    }

    public function test_paid_display_expires_ten_seconds_after_payment_even_when_retried(): void
    {
        $this->actingAs($this->cashier());
        $state = [...$this->state(), 'status' => 'paid', 'order_id' => 42, 'payment_method' => 'CASH', 'change' => 25000];

        $this->postJson(route('admin.pos.customer-display.sync'), $state)->assertOk();
        $initialExpiry = $this->getJson(route('admin.pos.customer-display.snapshot'))
            ->assertJsonPath('state.change', 25000)->json('state.paid_until');
        $this->travel(3)->seconds();
        $this->postJson(route('admin.pos.customer-display.sync'), $state)->assertOk();
        $this->getJson(route('admin.pos.customer-display.snapshot'))->assertJsonPath('state.paid_until', $initialExpiry);
        $this->travel(7)->seconds();
        $this->getJson(route('admin.pos.customer-display.snapshot'))->assertJsonPath('state', null);
    }

    public function test_kiosk_action_waits_for_cashier_and_repeated_delivery_keeps_one_action(): void
    {
        Event::fake([PosCustomerAction::class]);
        $cashier = $this->cashier();
        $product = Product::factory()->create(['shop_id' => $cashier->shop_id, 'name' => 'Jawara', 'is_sold_out' => false]);
        $action = [
            'action_id' => (string) Str::uuid(),
            'action' => 'add_item',
            'item' => ['id' => $product->id, 'line_key' => (string) Str::uuid(), 'qty' => 1, 'serving_type' => 'TAKEAWAY'],
        ];

        $this->actingAs($cashier);
        $this->postJson(route('admin.pos.customer-kiosk.action'), $action)->assertOk();
        $this->postJson(route('admin.pos.customer-kiosk.action'), $action)->assertOk();
        $this->getJson(route('admin.pos.customer-display.snapshot'))->assertJsonCount(1, 'actions')
            ->assertJsonPath('actions.0.action_id', $action['action_id']);
        Event::assertDispatchedTimes(PosCustomerAction::class, 1);
        Event::assertDispatched(PosCustomerAction::class, fn (PosCustomerAction $event) => $event->broadcastAs() === 'pos.customer.action'
            && $event->broadcastOn()[0]->name === 'private-shop.'.$cashier->shop_id.'.pos-display'
            && $event->broadcastWith()['action_id'] === $action['action_id']);

        $state = [...$this->state(), 'acknowledged_action_ids' => [$action['action_id']]];
        $this->postJson(route('admin.pos.customer-display.sync'), $state)->assertOk();
        $this->postJson(route('admin.pos.customer-kiosk.action'), $action)->assertOk();
        $this->getJson(route('admin.pos.customer-display.snapshot'))->assertJsonCount(0, 'actions');
        Event::assertDispatchedTimes(PosCustomerAction::class, 1);
    }

    public function test_kiosk_rejects_invalid_actions_and_products_from_other_shops(): void
    {
        Event::fake([PosCustomerAction::class]);
        $this->actingAs($this->cashier());
        $otherProduct = Product::factory()->create(['is_sold_out' => false]);
        $this->postJson(route('admin.pos.customer-kiosk.action'), ['action_id' => 'invalid', 'action' => 'add_item'])
            ->assertUnprocessable()->assertJsonValidationErrors(['action_id', 'item']);
        $this->postJson(route('admin.pos.customer-kiosk.action'), [
            'action_id' => (string) Str::uuid(),
            'action' => 'add_item',
            'item' => ['id' => $otherProduct->id, 'line_key' => (string) Str::uuid(), 'qty' => 1, 'serving_type' => 'DINE_IN'],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        Event::assertNotDispatched(PosCustomerAction::class);
    }

    private function cashier(?Shop $shop = null): User
    {
        return User::factory()->create([
            'shop_id' => ($shop ?? Shop::factory()->create(['onboarding_status' => 'completed']))->id,
            'role' => 'cashier',
        ]);
    }

    private function state(): array
    {
        return [
            'status' => 'ordering',
            'items' => [['name' => 'Jawara', 'qty' => 1, 'price' => 25000, 'subtotal' => 25000]],
            'subtotal' => 25000,
            'grand_total' => 25000,
        ];
    }
}
