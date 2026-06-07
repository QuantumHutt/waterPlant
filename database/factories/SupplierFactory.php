<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'code' => 'SUP-'.$this->faker->unique()->numberBetween(10000, 99999),
            'name' => $this->faker->company(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->optional()->companyEmail(),
            'address' => $this->faker->address(),
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ];
    }
}
