<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Product;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'name' => fake()->word(),
            'price' => fake()->randomFloat(2, 1, 999),
            'category' => fake()->word(),
            'stock_quantity' => fake()->randomFloat(2, 0, 1000),
        ];
    }
}
