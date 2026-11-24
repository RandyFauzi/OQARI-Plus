<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class McpAccess
{
    public function role(User $user): ?string
    {
        if (! $user->isAccountActive()) {
            return null;
        }
        if ($user->role === 'superadmin' && $user->shop_id === null) {
            return 'superadmin';
        }
        if (! $user->shop || $user->shop->isSuspended()) {
            return null;
        }
        if ($user->role === 'owner') {
            return 'owner';
        }

        return ($user->shop->branches_enabled ? $this->branches($user)->isNotEmpty() : $user->role === 'manager')
            ? 'manager' : null;
    }

    /** @return Collection<int, Branch> */
    public function branches(User $user, ?McpConnection $connection = null): Collection
    {
        if (! $user->shop?->branches_enabled) {
            return new Collection;
        }

        return Branch::where('shop_id', $user->shop_id)->where('is_active', true)
            ->when($user->role !== 'owner', fn ($query) => $query->whereHas('memberships', fn ($membership) => $membership
                ->where('user_id', $user->id)->where('role', 'manager')))
            ->when($connection?->branch_ids !== null, fn ($query) => $query->whereIn('id', $connection->branch_ids))
            ->orderBy('name')->orderBy('id')->get();
    }

    /** @return list<string> */
    public function abilities(User $user, string $role): array
    {
        if ($role === 'superadmin') {
            return ['platform'];
        }

        $permissions = $role === 'manager' && $user->shop->branches_enabled
            ? array_intersect(config('access.branch_defaults.manager'), $user->permissions ?? array_keys(config('access.permissions')))
            : $user->effectivePermissions();

        return collect($role === 'owner' ? ['sales', 'inventory', 'operations'] : ['sales', 'inventory'])
            ->filter(fn (string $ability) => in_array(config('oqari_mcp.abilities.'.$ability.'.gate'), $permissions, true))
            ->values()->all();
    }

    public function allows(McpConnection $connection, string $ability): bool
    {
        $roleAbilities = $this->abilities($connection->user, $connection->role);
        if (! in_array($ability, $roleAbilities, true)) {
            return false;
        }

        if ($connection->oauth_client_id !== null) {
            return in_array($ability, $connection->abilities, true);
        }

        return true;
    }
}
