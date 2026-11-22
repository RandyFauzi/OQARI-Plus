<?php

namespace Tests\Feature;

use App\Actions\Branch\ManageBranches;
use App\Models\Branch;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_all_accounts_with_branch_labels_and_can_assign_branches(): void
    {
        [$owner, $managerA, $workerA, $workerB, $branchA, $branchB] = $this->setupTeam();

        // Owner sees all accounts across branches and branch labels on /admin/users
        $this->actingAs($owner)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($workerA->email)
            ->assertSee($workerB->email)
            ->assertSee($branchA->name)
            ->assertSee($branchB->name);

        // Filter by branch B only shows Branch B staff
        $this->actingAs($owner)
            ->get(route('admin.users.index', ['branch_id' => $branchB->id]))
            ->assertOk()
            ->assertSee($workerB->email)
            ->assertDontSee($workerA->email);

        // Owner creates a new worker assigned to Branch B from Business Mode
        $this->actingAs($owner)
            ->post(route('admin.users.store'), [
                'name' => 'Barista Baru',
                'email' => 'barista.baru@example.com',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'role' => 'barista',
                'permission_mode' => 'role',
                'branch_assignment_submitted' => '1',
                'branch_ids' => [$branchB->id],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'barista.baru@example.com')->firstOrFail();
        $this->assertTrue($created->branchMemberships()->where('branch_id', $branchB->id)->exists());
        $this->assertFalse($created->branchMemberships()->where('branch_id', $branchA->id)->exists());
    }

    public function test_branch_manager_can_only_crud_workers_under_their_own_branch(): void
    {
        [$owner, $managerA, $workerA, $workerB, $branchA, $branchB] = $this->setupTeam();

        // Manager A sees worker A, NOT worker B
        $this->actingAs($managerA)
            ->get("/cabang/{$branchA->slug}/admin/users")
            ->assertOk()
            ->assertSee($workerA->email)
            ->assertDontSee($workerB->email);

        // Manager A can create a worker under Branch A
        $this->actingAs($managerA)
            ->post("/cabang/{$branchA->slug}/admin/users", [
                'name' => 'Kasir Alpha 2',
                'email' => 'kasir.alpha2@example.com',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'role' => 'cashier',
                'permission_mode' => 'role',
            ])
            ->assertRedirect();

        $newWorker = User::where('email', 'kasir.alpha2@example.com')->firstOrFail();
        $this->assertTrue($newWorker->branchMemberships()->where('branch_id', $branchA->id)->exists());

        // Manager A can update & archive worker under Branch A
        $this->actingAs($managerA)
            ->put("/cabang/{$branchA->slug}/admin/users/{$newWorker->id}", [
                'name' => 'Kasir Alpha Updated',
                'email' => 'kasir.alpha2@example.com',
                'role' => 'barista',
                'permission_mode' => 'role',
                'is_active' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('barista', $newWorker->fresh()->role);

        $this->actingAs($managerA)
            ->delete("/cabang/{$branchA->slug}/admin/users/{$newWorker->id}")
            ->assertRedirect();
        $this->assertNotNull($newWorker->fresh()->archived_at);

        // Manager A CANNOT edit or delete Worker B (different branch)
        $this->actingAs($managerA)
            ->get("/cabang/{$branchA->slug}/admin/users/{$workerB->id}/edit")
            ->assertForbidden();

        $this->actingAs($managerA)
            ->put("/cabang/{$branchA->slug}/admin/users/{$workerB->id}", [
                'name' => 'Hijacked',
                'email' => $workerB->email,
                'role' => 'cashier',
            ])
            ->assertForbidden();

        $this->actingAs($managerA)
            ->delete("/cabang/{$branchA->slug}/admin/users/{$workerB->id}")
            ->assertForbidden();

        // Manager A CANNOT create a manager/admin or pass branch_ids
        $this->actingAs($managerA)
            ->postJson("/cabang/{$branchA->slug}/admin/users", [
                'name' => 'Fake Manager',
                'email' => 'fake.mgr@example.com',
                'password' => 'StrongPass123!',
                'password_confirmation' => 'StrongPass123!',
                'role' => 'manager',
                'branch_ids' => [$branchB->id],
            ])
            ->assertStatus(422);
    }

    /** @return array{User, User, User, User, Branch, Branch} */
    private function setupTeam(): array
    {
        $shop = Shop::factory()->create(['onboarding_status' => 'completed']);
        $owner = User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner']);
        $branches = app(ManageBranches::class);
        $branchA = $branches->enable($shop, 'Outlet Alpha');
        $branchB = $branches->create($shop, 'Outlet Bravo');

        $managerA = User::factory()->create(['shop_id' => $shop->id, 'role' => 'manager', 'email' => 'manager.a@example.com']);
        $branchA->memberships()->create(['user_id' => $managerA->id, 'role' => 'manager']);

        $workerA = User::factory()->create(['shop_id' => $shop->id, 'role' => 'cashier', 'email' => 'kasir.a@example.com']);
        $branchA->memberships()->create(['user_id' => $workerA->id, 'role' => 'cashier']);

        $workerB = User::factory()->create(['shop_id' => $shop->id, 'role' => 'cashier', 'email' => 'kasir.b@example.com']);
        $branchB->memberships()->create(['user_id' => $workerB->id, 'role' => 'cashier']);

        return [$owner, $managerA, $workerA, $workerB, $branchA, $branchB];
    }
}
