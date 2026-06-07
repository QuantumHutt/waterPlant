<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'is_default' => true, 'is_active' => true],
        );

        Warehouse::firstOrCreate(
            ['code' => 'PLANT'],
            ['name' => 'Plant Floor', 'is_default' => false, 'is_active' => true],
        );
    }
}
