<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(Str::random(8)),
            'barcode' => (string) $this->faker->unique()->ean13(),
            'name' => $this->faker->randomElement(['250ml', '500ml', '1L', '1.5L', '6L', '19L']),
            'capacity_ml' => $this->faker->randomElement([250, 500, 1000, 1500, 6000, 19000]),
            'cost_price' => $this->faker->numberBetween(5, 100),
            'sale_price' => $this->faker->numberBetween(20, 200),
            'reorder_level' => $this->faker->numberBetween(10, 50),
            'is_active' => true,
        ];
    }
}
