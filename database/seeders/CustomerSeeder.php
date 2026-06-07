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

        $samples = [
            ['Al-Madina Store', 'wholesale', 50000],
            ['Hassan General Store', 'retail', 10000],
            ['City Office Tower', 'corporate', 100000],
        ];

        foreach ($samples as [$name, $type, $limit]) {
            Customer::firstOrCreate(['name' => $name], [
                'code' => 'CUST-'.strtoupper(substr(md5($name), 0, 5)),
                'type' => $type,
                'phone' => '0300'.random_int(1000000, 9999999),
                'credit_limit' => $limit,
                'is_active' => true,
            ]);
        }
    }
}
