<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_paid_sales_and_top_three_menus_per_branch(): void
    {
        $this->freezeTime();
        [$owner, $a, $b] = $this->business();
        $midnight = CarbonImmutable::today(config('attendance.timezone'));
        $order = $this->order($a, $midnight, 100000);
        foreach (['Jawara' => 8, 'Sultan' => 6, 'Garuda' => 4, 'Jagoan' => 2] as $name => $units) {
            $this->item($order, $name, $units);
        }
        $this->item($order, 'Menu Dibatalkan', 99, 'cancelled');
        $this->item($this->order($b, $midnight->subDay(), 50000), 'Menu Outlet B', 3);
        $this->item($this->order($a, $midnight, 900000, 'UNPAID'), 'Menu Belum Lunas', 99);
        $this->item($this->order($a, $midnight, 800000, 'PAID', 'CANCELLED'), 'Menu Pesanan Batal', 99);
        $this->order($a, $midnight->addDay(), 900000);
        $this->order($a, $midnight->subWeek()->subMinute(), 100000);
        $other = Branch::factory()->create();
        $this->item($this->order($other, $midnight, 700000), 'Menu Bisnis Lain', 99);

        $response = $this->actingAs($owner)->get(route('admin.owner.dashboard'))
            ->assertOk()->assertSee('Dashboard Owner')->assertSee('Penjualan per cabang')->assertDontSee('Tambah cabang')
            ->assertSee('Jawara')->assertSee('Menu Outlet B')->assertDontSee('Jagoan')
            ->assertDontSee('Menu Dibatalkan')->assertDontSee('Menu Belum Lunas')
            ->assertDontSee('Menu Pesanan Batal')->assertDontSee('Menu Bisnis Lain');
        $stats = $response->viewData('dashboard');

        $this->get(route('admin.branches.index'))->assertOk()->assertSee('Daftar Outlet')
            ->assertSee('Tambah cabang')->assertDontSee('Tren penjualan')->assertDontSee('Menu terlaris per cabang');
        $this->assertEquals(150000, $stats['sales']);
        $this->assertEquals(110000, $stats['grossProfit']);
        $this->assertEquals(2, $stats['orders']);
        $this->assertEquals(75000, $stats['average']);
        $this->assertEquals(50, $stats['salesChange']);
        $this->assertCount(7, $stats['trend']);
        $this->assertEquals(100000, $stats['trend']->last()['sales']);
        $this->assertEquals(50000, $stats['trend'][5]['sales']);
        $this->assertSame(['Jawara', 'Sultan', 'Garuda'], $stats['topProducts']->get($a->id)->pluck('product_name')->all());
    }

    public function test_filter_limits_summary_trend_and_products_while_retaining_branch_comparison(): void
    {
        [$owner, $a, $b] = $this->business();
        $midnight = CarbonImmutable::today(config('attendance.timezone'));
        $this->item($this->order($a, $midnight, 100000), 'Jawara', 5);
        $this->item($this->order($b, $midnight, 50000), 'Menu Outlet B', 3);
        $this->item($this->order($b, $midnight->subMinute(), 20000), 'Menu Di Luar Hari Ini', 8);

        $response = $this->actingAs($owner)->get(route('admin.owner.dashboard', ['days' => 1, 'branch' => $b->slug]))
            ->assertOk()->assertSee('Menu Outlet B')->assertDontSee('Jawara')->assertDontSee('Menu Di Luar Hari Ini');
        $stats = $response->viewData('dashboard');

        $this->assertEquals(50000, $stats['sales']);
        $this->assertCount(1, $stats['trend']);
        $this->assertEquals(50000, $stats['trend']->first()['sales']);
        $this->assertEquals(100000, $stats['branchStats'][$a->id]['sales']);
        $this->assertEquals(150, $stats['salesChange']);

        $this->getJson(route('admin.owner.dashboard', ['days' => 365]))->assertUnprocessable()->assertJsonValidationErrors('days');
        $this->get(route('admin.owner.dashboard', ['branch' => 'cabang-tidak-tersedia']))->assertNotFound();
    }

    public function test_employee_only_sees_assigned_branch_directory(): void
    {
        [$owner, $a, $b] = $this->business();
        $staff = User::factory()->create(['shop_id' => $owner->shop_id, 'role' => 'manager']);
        app(ManageBranches::class)->assign($b, $staff, 'manager');

        $this->actingAs($staff)->get(route('admin.owner.dashboard'))->assertForbidden();
        $this->get(route('admin.branches.index', ['branch' => $a->slug]))
            ->assertOk()->assertSee('Cabang Saya')->assertSee($b->name)
            ->assertDontSee($a->name)->assertDontSee('Menu terlaris per cabang');
    }

    private function business(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $branches = app(ManageBranches::class);

        return [$owner, $branches->enable($shop, 'Outlet Alpha'), $branches->create($shop, 'Outlet Bravo')];
    }

    private function order(Branch $branch, CarbonImmutable $date, int $sales, string $payment = 'PAID', string $status = 'COMPLETED'): Order
    {
        return OrderFactory::new()->create([
            'shop_id' => $branch->shop_id, 'branch_id' => $branch->id, 'created_at' => $date->utc(),
            'subtotal' => $sales + 5000, 'discount_amount' => 5000, 'grand_total' => $sales, 'total_cogs' => 20000,
            'payment_status' => $payment, 'order_status' => $status, 'payment_method' => 'CASH',
        ]);
    }

    private function item(Order $order, string $name, int $units, string $status = 'new'): void
    {
        $order->items()->create([
            'product_name' => $name, 'quantity' => $units, 'status' => $status,
            'unit_price' => 10000, 'subtotal' => $units * 10000,
        ]);
    }
}
