<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            'Pak Plastics (Bottles)',
            'Crystal Caps & Closures',
            'Premium Label Printers',
            'AquaChem Supplies',
        ];

        foreach ($samples as $name) {
            Supplier::firstOrCreate(['name' => $name], [
                'code' => 'SUP-'.strtoupper(substr(md5($name), 0, 5)),
                'phone' => '042'.random_int(1000000, 9999999),
                'is_active' => true,
            ]);
        }
    }
}
