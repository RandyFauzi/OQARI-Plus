<?php

namespace App\Actions\Branch;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\InventoryLocation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageBranches
{
    public function enable(Shop $shop, string $primaryName = 'Outlet Utama'): Branch
    {
        return DB::transaction(function () use ($shop, $primaryName): Branch {
            $lockedShop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            if ($lockedShop->branches_enabled) {
                return $lockedShop->branches()->where('is_primary', true)->sole();
            }

            $branch = $lockedShop->branches()->create([
                'name' => $primaryName, 'slug' => Str::slug($primaryName), 'is_primary' => true, 'is_active' => true,
            ]);
            $outlet = InventoryLocation::firstOrCreate(
                ['shop_id' => $lockedShop->id, 'code' => InventoryLocation::Outlet],
                ['name' => $primaryName],
            );
            $outlet->update(['branch_id' => $branch->id, 'name' => $primaryName]);

            foreach (['orders', 'tables', 'cash_registers', 'attendances', 'crew_shifts', 'shift_templates',
                'printers', 'outlet_stock_requests', 'petty_purchases', 'activity_logs'] as $table) {
                DB::table($table)->where('shop_id', $lockedShop->id)->whereNull('branch_id')
                    ->update(['branch_id' => $branch->id]);
            }
            foreach (['stock_counts', 'stock_mutations'] as $table) {
                DB::table($table)->where('shop_id', $lockedShop->id)->whereNull('branch_id')
                    ->where('inventory_location_id', $outlet->id)->update(['branch_id' => $branch->id]);
            }

            User::where('shop_id', $lockedShop->id)->unarchived()
                ->whereIn('role', array_keys(config('access.roles')))
                ->eachById(fn (User $user) => $branch->memberships()->create(['user_id' => $user->id, 'role' => $user->role]));

            if (! CashRegister::where('shop_id', $lockedShop->id)->where('branch_id', $branch->id)->where('is_active', true)->exists()) {
                CashRegister::create(['shop_id' => $lockedShop->id, 'branch_id' => $branch->id, 'name' => 'Kasir 1', 'is_active' => true]);
            }
            $lockedShop->forceFill(['branches_enabled' => true])->save();
            $shop->branches_enabled = true;

            return $branch;
        }, 3);
    }

    public function create(Shop $shop, string $name, ?string $address = null): Branch
    {
        return DB::transaction(function () use ($shop, $name, $address): Branch {
            $shop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            if (! $shop->branches_enabled) {
                $this->enable($shop);
            }
            $slug = Str::limit(Str::slug($name), 64, '');
            if ($slug === '' || $shop->branches()->where('slug', $slug)->exists()) {
                throw ValidationException::withMessages(['name' => 'Gunakan nama cabang yang unik di bisnis ini.']);
            }

            $branch = $shop->branches()->create(['slug' => $slug, 'name' => $name, 'address' => $address, 'is_active' => true]);
            InventoryLocation::create(['shop_id' => $shop->id, 'branch_id' => $branch->id, 'code' => 'outlet-'.$branch->id, 'name' => $name]);
            CashRegister::create(['shop_id' => $shop->id, 'branch_id' => $branch->id, 'name' => 'Kasir 1', 'is_active' => true]);

            return $branch;
        }, 3);
    }

    public function assign(Branch $branch, User $user, string $role): void
    {
        abort_unless((int) $user->shop_id === (int) $branch->shop_id &&
            $user->isAccountActive() && ! in_array($user->role, ['owner', 'superadmin'], true) &&
            array_key_exists($role, config('access.roles')), 404);

        $branch->memberships()->updateOrCreate(['user_id' => $user->id], ['role' => $role]);
    }
}
