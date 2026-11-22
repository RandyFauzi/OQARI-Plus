<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => fn (array $attributes) => User::factory()->create([
                'shop_id' => Branch::findOrFail($attributes['branch_id'])->shop_id,
                'role' => $attributes['role'],
            ])->id,
            'role' => 'cashier',
        ];
    }
}
