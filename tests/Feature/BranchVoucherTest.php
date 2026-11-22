<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Models\Branch;
use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_crud_vouchers_while_staff_only_views_active_branch_vouchers(): void
    {
        [$owner, $managerB, $branchA, $branchB] = $this->setupBusinessWithStaff();

        // Owner creates global voucher, Branch B active voucher, Branch A active voucher, and inactive Branch B voucher
        $this->actingAs($owner)
            ->postJson('/admin/api/vouchers', [
                'code' => 'GLOBAL10',
                'name' => 'Diskon Global',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'branch_id' => null,
                'is_active' => true,
            ])
            ->assertOk();

        $resB = $this->actingAs($owner)
            ->postJson('/admin/api/vouchers', [
                'code' => 'BRAVO20',
                'name' => 'Khusus Bravo',
                'discount_type' => 'fixed',
                'discount_value' => 5000,
                'branch_id' => $branchB->id,
                'is_active' => true,
            ])
            ->assertOk();
        $voucherBId = $resB->json('voucher.id');

        $this->actingAs($owner)
            ->postJson('/admin/api/vouchers', [
                'code' => 'ALPHA15',
                'name' => 'Khusus Alpha',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'branch_id' => $branchA->id,
                'is_active' => true,
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->postJson('/admin/api/vouchers', [
                'code' => 'BRAVO_OFF',
                'name' => 'Bravo Nonaktif',
                'discount_type' => 'fixed',
                'discount_value' => 3000,
                'branch_id' => $branchB->id,
                'is_active' => false,
            ])
            ->assertOk();

        // Manager in Branch B can view ONLY active vouchers for Global + Branch B
        $listResponse = $this->actingAs($managerB)
            ->getJson("/cabang/{$branchB->slug}/admin/api/vouchers")
            ->assertOk()
            ->assertJsonPath('can_manage_vouchers', false);

        $codes = collect($listResponse->json('vouchers'))->pluck('code')->all();
        $this->assertEqualsCanonicalizing(['GLOBAL10', 'BRAVO20'], $codes);

        // Non-owner cannot create, update, toggle, or delete vouchers
        $this->actingAs($managerB)
            ->postJson("/cabang/{$branchB->slug}/admin/api/vouchers", [
                'code' => 'ILLEGAL',
                'name' => 'Illegal',
                'discount_type' => 'fixed',
                'discount_value' => 1000,
            ])
            ->assertForbidden();

        $this->actingAs($managerB)
            ->putJson("/cabang/{$branchB->slug}/admin/api/vouchers/{$voucherBId}", [
                'code' => 'BRAVO20',
                'name' => 'Edited',
                'discount_type' => 'fixed',
                'discount_value' => 9000,
            ])
            ->assertForbidden();

        $this->actingAs($managerB)
            ->patchJson("/cabang/{$branchB->slug}/admin/api/vouchers/{$voucherBId}/toggle")
            ->assertForbidden();

        $this->actingAs($managerB)
            ->deleteJson("/cabang/{$branchB->slug}/admin/api/vouchers/{$voucherBId}")
            ->assertForbidden();

        // Owner can toggle & delete
        $this->actingAs($owner)
            ->patchJson("/admin/api/vouchers/{$voucherBId}/toggle")
            ->assertOk()
            ->assertJsonPath('voucher.is_active', false);

        $this->actingAs($owner)
            ->deleteJson("/admin/api/vouchers/{$voucherBId}")
            ->assertOk();

        $this->assertDatabaseMissing('vouchers', ['id' => $voucherBId]);
    }

    public function test_branch_specific_voucher_is_only_valid_in_target_branch(): void
    {
        [$owner, $managerB, $branchA, $branchB] = $this->setupBusinessWithStaff();
        $shop = $owner->shop;

        Voucher::create([
            'shop_id' => $shop->id,
            'branch_id' => $branchB->id,
            'code' => 'ONLYB',
            'name' => 'Promo Cabang B',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'min_spend' => 0,
            'is_active' => true,
        ]);

        // Customer storefront validation
        $this->postJson(route('shop.branch.voucher.validate', ['slug' => $shop->slug, 'branchSlug' => $branchB->slug]), [
            'code' => 'ONLYB',
            'subtotal' => 50000,
        ])->assertOk()->assertJsonPath('discount_amount', 10000);

        $this->postJson(route('shop.branch.voucher.validate', ['slug' => $shop->slug, 'branchSlug' => $branchA->slug]), [
            'code' => 'ONLYB',
            'subtotal' => 50000,
        ])->assertStatus(422);

        // Staff POS validation in Branch B succeeds, Branch A fails
        $this->actingAs($managerB)
            ->postJson("/cabang/{$branchB->slug}/admin/api/vouchers/validate", [
                'code' => 'ONLYB',
                'subtotal' => 50000,
            ])
            ->assertOk();

        $this->actingAs($owner)
            ->postJson("/cabang/{$branchA->slug}/admin/api/vouchers/validate", [
                'code' => 'ONLYB',
                'subtotal' => 50000,
            ])
            ->assertStatus(422);
    }

    /** @return array{User, User, Branch, Branch} */
    private function setupBusinessWithStaff(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $branches = app(ManageBranches::class);
        $branchA = $branches->enable($shop, 'Outlet Alpha');
        $branchB = $branches->create($shop, 'Outlet Bravo');

        $managerB = User::factory()->create(['shop_id' => $shop->id, 'role' => 'manager']);
        $branchB->memberships()->create(['user_id' => $managerB->id, 'role' => 'manager']);

        return [$owner, $managerB, $branchA, $branchB];
    }
}
