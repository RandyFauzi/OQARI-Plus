<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchContext
{
    private ?Branch $branch = null;

    private ?string $role = null;

    private ?int $userId = null;

    public function select(Branch $branch, ?string $role = null, ?int $userId = null): void
    {
        $this->branch = $branch;
        $this->role = $role;
        $this->userId = $userId;
    }

    public function clear(): void
    {
        $this->branch = null;
        $this->role = null;
        $this->userId = null;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function id(?int $shopId = null): ?int
    {
        if ($shopId !== null && (int) $this->branch?->shop_id !== $shopId) {
            return null;
        }

        return $this->branch?->id;
    }

    public function roleFor(User $user): ?string
    {
        return $this->userId !== null && $this->userId === $user->id ? $this->role : null;
    }

    public function employees(Builder $users): Builder
    {
        return $users->when($this->id(), fn (Builder $query, int $branchId) => $query
            ->whereHas('branchMemberships', fn (Builder $memberships) => $memberships->where('branch_id', $branchId)));
    }

    public function accessible(User $user): Builder
    {
        return Branch::where('shop_id', $user->shop_id)->where('is_active', true)
            ->when($user->role !== 'owner', fn (Builder $query) => $query
                ->whereHas('memberships', fn (Builder $memberships) => $memberships->where('user_id', $user->id)));
    }

    public function prefix(): string
    {
        return $this->branch ? '/cabang/'.$this->branch->slug : '';
    }
}
