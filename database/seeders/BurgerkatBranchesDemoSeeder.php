<?php

namespace Database\Seeders;

use App\Actions\Branch\ManageBranches;
use App\Models\Shop;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BurgerkatBranchesDemoSeeder extends Seeder
{
    public function run(ManageBranches $branches): void
    {
        if (! app()->environment('local', 'staging')) {
            throw new \RuntimeException('Seeder Burgerkat hanya untuk lokal atau staging.');
        }

        DB::transaction(function () use ($branches): void {
            $shop = Shop::where('slug', 'burgerkat-demo')->where('name', 'Burgerkat Demo')->lockForUpdate()->sole();
            $owner = User::where('shop_id', $shop->id)->where('role', 'owner')->where('is_active', true)->unarchived()->sole();
            $primary = $branches->enable($shop, 'Outlet Demo');
            $owner->branchMemberships()->whereHas('branch', fn ($query) => $query->where('shop_id', $shop->id))->delete();

            foreach (['outlet-demo' => 'Outlet Demo', 'panakkukang' => 'Outlet Panakkukang (Demo)', 'tamalanrea' => 'Outlet Tamalanrea (Demo)'] as $code => $name) {
                $branch = $code === 'outlet-demo' ? $primary : (
                    $shop->branches()->where('slug', Str::slug($name))->first() ?? $branches->create($shop, $name)
                );
                foreach (['manager' => 'Kepala Cabang', 'cashier' => 'Kasir', 'kitchen' => 'Dapur'] as $role => $label) {
                    $email = $role.'.'.$code.'@burgerkat.test';
                    $staff = User::where('email', $email)->first();
                    if ($staff && (int) $staff->shop_id !== (int) $shop->id) {
                        throw new \RuntimeException('Email demo sudah digunakan bisnis lain.');
                    }
                    $staff ??= User::create([
                        'shop_id' => $shop->id, 'name' => $label.' '.$name, 'email' => $email,
                        'role' => $role, 'password' => Hash::make(Str::random(32)),
                    ]);
                    $branches->assign($branch, $staff, $role);
                }

                Table::firstOrCreate(['shop_id' => $shop->id, 'branch_id' => $branch->id, 'name' => 'Meja 1']);

                $this->command?->info($branch->name.' · Kepala Cabang, Kasir, Dapur');
            }

            $this->command?->info('Owner bisnis: '.$owner->email);
        }, 3);
    }
}
