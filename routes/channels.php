<?php

use App\Models\CashRegister;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('shop.{shopId}.pos-display', function (User $user, int $shopId): bool {
    return $user->shop_id === $shopId && ! $user->shop?->branches_enabled && $user->can('access-pos');
});

Broadcast::channel('shop.{shopId}.orders', function (User $user, int $shopId): bool {
    return $user->shop_id === $shopId && ! $user->shop?->branches_enabled && $user->can('view-orders');
});

$authorizeBranchChannel = function (User $user, int $shopId, int $branchId, string $ability): bool {
    if ($user->shop_id !== $shopId || ! $user->isAccountActive() || ! $user->shop?->branches_enabled || $user->shop->isSuspended()) {
        return false;
    }
    $context = app(BranchContext::class);
    $branch = $context->accessible($user)->find($branchId);
    if (! $branch) {
        return false;
    }
    $role = $user->role === 'owner' ? 'owner' : $branch->memberships()->where('user_id', $user->id)->value('role');
    if ($role !== 'owner' && ! array_key_exists($role ?? '', config('access.roles'))) {
        return false;
    }
    $context->select($branch, $role, $user->id);
    try {
        return $user->can($ability);
    } finally {
        $context->clear();
    }
};

Broadcast::channel('shop.{shopId}.branch.{branchId}.orders', fn (User $user, int $shopId, int $branchId): bool => $authorizeBranchChannel($user, $shopId, $branchId, 'view-orders'));

Broadcast::channel('shop.{shopId}.terminal.{terminalId}.pos-display', function (User $user, int $shopId, int $terminalId) use ($authorizeBranchChannel): bool {
    $branchId = CashRegister::withoutGlobalScope('branch')->where('shop_id', $shopId)->where('is_active', true)->whereKey($terminalId)->value('branch_id');

    return $branchId !== null && $authorizeBranchChannel($user, $shopId, (int) $branchId, 'access-pos');
});
