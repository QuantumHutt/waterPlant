<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'code' => 'CUST-'.$this->faker->unique()->numberBetween(10000, 99999),
            'name' => $this->faker->name(),
            'type' => $this->faker->randomElement(['retail', 'wholesale', 'corporate']),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->optional()->safeEmail(),
            'address' => $this->faker->address(),
            'credit_limit' => $this->faker->randomElement([0, 5000, 20000, 50000]),
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ];
    }
}
