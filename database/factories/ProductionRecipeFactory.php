<?php

namespace Database\Factories;

use App\Models\ProductionRecipe;
use App\Models\RawMaterial;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductionRecipe> */
class ProductionRecipeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'output_material_id' => function (array $attributes): int {
                return RawMaterial::create([
                    'shop_id' => $attributes['shop_id'], 'name' => fake()->unique()->word(), 'unit' => 'ml',
                ])->id;
            },
            'request_id' => (string) Str::uuid(),
            'version' => 1,
            'yield_quantity' => 5000,
            'unit' => 'ml',
        ];
    }
}
