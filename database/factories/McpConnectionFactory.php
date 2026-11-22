<?php

namespace Database\Factories;

use App\Models\McpConnection;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<McpConnection> */
class McpConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'user_id' => fn (array $attributes) => User::factory()->create(['shop_id' => $attributes['shop_id'], 'role' => 'owner'])->id,
            'name' => 'MCP laporan',
            'role' => 'owner',
            'abilities' => ['sales'],
            'account_version' => 0,
            'token_hash' => hash('sha256', fake()->uuid()),
            'expires_at' => now()->addDay(),
        ];
    }
}
