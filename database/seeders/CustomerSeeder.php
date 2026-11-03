<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::firstOrCreate(['code' => 'WALK-IN'], [
            'name' => 'Walk-in Customer',
            'type' => 'walk_in',
            'is_active' => true,
        ]);

        // Sample SHOP customers (type wholesale → market price at the POS).
        $samples = [
            ['Sample Shop A', 50000],
            ['Sample Shop B', 25000],
        ];

        foreach ($samples as [$name, $limit]) {
            Customer::firstOrCreate(['name' => $name], [
                'code' => 'CUST-'.strtoupper(substr(md5($name), 0, 5)),
                'type' => 'wholesale',
                'credit_limit' => $limit,
                'is_active' => true,
            ]);
        }
    }
}
