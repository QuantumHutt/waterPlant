<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Enums\ProductType;
use App\Support\Enums\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = $this->faker->numberBetween(5, 200);

        return [
            'category_id' => ProductCategory::factory(),
            'sku' => strtoupper(Str::random(6)).'-'.$this->faker->unique()->numberBetween(10000, 99999),
            'barcode' => (string) $this->faker->unique()->ean13(),
            'name' => ucfirst($this->faker->words(2, true)),
            'description' => $this->faker->optional()->sentence(),
            'type' => $this->faker->randomElement(ProductType::cases())->value,
            'unit' => $this->faker->randomElement(ProductUnit::cases())->value,
            'cost_price' => $cost,
            'sale_price' => $cost * 1.4,
            'wholesale_price' => $cost * 1.2,
            'reorder_level' => $this->faker->numberBetween(10, 100),
            'opening_stock' => 0,
            'track_inventory' => true,
            'is_active' => true,
        ];
    }

    public function finished(): static
    {
        return $this->state(fn () => ['type' => ProductType::Finished->value]);
    }
}
