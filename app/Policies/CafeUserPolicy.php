<?php

namespace App\Policies;

use App\Models\User;
use App\Services\BranchContext;

class CafeUserPolicy
{
    public function update(User $actor, User $target): bool
    {
        if ($target->archived_at !== null || ! $actor->shop_id || (int) $actor->shop_id !== (int) $target->shop_id || $actor->id === $target->id || in_array($target->role, ['owner', 'superadmin'], true)) {
            return false;
        }
        if (! $actor->can('manage-users')) {
            return false;
        }
        if ($actor->role === 'owner') {
            return true;
        }
        if (! in_array($target->role, ['cashier', 'barista', 'kitchen', 'crew'], true)) {
            return false;
        }
        if ($actor->shop?->branches_enabled) {
            $branchId = app(BranchContext::class)->id();

            return $branchId !== null && $target->branchMemberships()->where('branch_id', $branchId)->exists();
        }

        return true;
    }

    public function delete(User $actor, User $target): bool
    {
        if ($actor->role === 'owner') {
            return $this->update($actor, $target);
        }

        return (bool) $actor->shop?->branches_enabled
            && in_array($actor->role, ['admin', 'manager'], true)
            && $this->update($actor, $target);
    }
}
