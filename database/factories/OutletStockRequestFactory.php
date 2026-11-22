<?php

namespace Database\Factories;

use App\Models\OutletStockRequest;
use App\Models\RawMaterial;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OutletStockRequest> */
class OutletStockRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'raw_material_id' => fn (array $attributes) => RawMaterial::create([
                'shop_id' => $attributes['shop_id'], 'name' => fake()->word(), 'unit' => 'pcs',
            ])->id,
            'request_id' => (string) Str::uuid(),
            'source' => 'warehouse', 'unit' => 'pcs', 'requested_quantity' => 10, 'status' => 'PENDING',
        ];
    }
}
