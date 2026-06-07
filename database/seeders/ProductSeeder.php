<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Enums\ProductType;
use App\Support\Enums\ProductUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Categories
        $categories = [
            'bottled_water' => 'Bottled Water',   // sealed, ready-made finished goods
            'service' => 'Services',              // refill service (no stock)
            'finished_goods' => 'Bulk Water',     // produced filtered water (litres)
            'bottle' => 'Bottles',
            'cap' => 'Caps',
            'label' => 'Labels',
            'chemical' => 'Chemicals',
            'filter' => 'Filters',
            'membrane' => 'RO Membranes',
            'packaging' => 'Packaging Material',
            'raw' => 'Raw Water',
        ];

        $cat = [];
        foreach ($categories as $type => $name) {
            $cat[$type] = ProductCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'type' => $type, 'is_active' => true],
            );
        }

        // 2. SEALED bottled-water products — each size is its own finished product.
        //    Selling these reduces finished-goods inventory only (water already accounted at production).
        $sealed = [
            ['SEAL-250', '250ml Bottle', 6, 20],
            ['SEAL-500', '500ml Bottle', 8, 30],
            ['SEAL-1000', '1 Liter Bottle', 12, 50],
            ['SEAL-1500', '1.5 Liter Bottle', 16, 70],
            ['SEAL-6000', '6 Liter Bottle', 45, 150],
        ];
        foreach ($sealed as [$sku, $name, $cost, $sale]) {
            Product::firstOrCreate(['sku' => $sku], [
                'category_id' => $cat['bottled_water']->id,
                'name' => $name,
                'type' => ProductType::Finished->value,
                'unit' => ProductUnit::Pcs->value,
                'water_litres' => 0,        // sealed goods don't deduct bulk water again
                'cost_price' => $cost,
                'sale_price' => $sale,
                'reorder_level' => 100,
                'track_inventory' => true,
                'is_active' => true,
            ]);
        }

        // 2b. Bulk FILTERED WATER — produced by batches, consumed by refills/new-bottle (in litres).
        Product::firstOrCreate(['sku' => 'WATER-BULK'], [
            'category_id' => $cat['finished_goods']->id,
            'name' => 'Filtered Water (Bulk)',
            'type' => ProductType::Finished->value,
            'unit' => ProductUnit::Litre->value,
            'water_litres' => 0,
            'cost_price' => 1.5,
            'sale_price' => 0,              // not sold directly
            'reorder_level' => 500,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        // 2c. 19L REFILL SERVICE — a service (no own stock). Selling it deducts 19 L of bulk water.
        Product::firstOrCreate(['sku' => '19L-REFILL'], [
            'category_id' => $cat['service']->id,
            'name' => '19 Liter Refill Service',
            'type' => ProductType::Finished->value,
            'is_service' => true,
            'unit' => ProductUnit::Pcs->value,
            'water_litres' => 19,          // deduct 19 L bulk water per refill
            'cost_price' => 30,
            'sale_price' => 200,           // refill price
            'track_inventory' => false,
            'is_active' => true,
        ]);

        // 2d. Company 19L EMPTY BOTTLE — physical asset issued on deposit for new-bottle sales.
        Product::firstOrCreate(['sku' => '19L-BOTTLE'], [
            'category_id' => $cat['bottle']->id,
            'name' => '19L Bottle (Company)',
            'type' => ProductType::Consumable->value,
            'unit' => ProductUnit::Pcs->value,
            'water_litres' => 0,
            'cost_price' => 700,
            'sale_price' => 0,             // not sold — issued on refundable deposit
            'reorder_level' => 20,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        // 3. Raw / consumable materials.
        $materials = [
            ['RAW-WATER', 'Raw Tap Water', 'raw', ProductType::Raw, ProductUnit::Litre, 0.05, 0],
            ['BTL-500', 'Empty 500ml Bottle', 'bottle', ProductType::Consumable, ProductUnit::Pcs, 4, 0],
            ['CAP-STD', 'Bottle Cap', 'cap', ProductType::Consumable, ProductUnit::Pcs, 0.8, 0],
            ['LBL-STD', 'Product Label', 'label', ProductType::Consumable, ProductUnit::Pcs, 0.5, 0],
            ['CHEM-CL', 'Chlorine', 'chemical', ProductType::Consumable, ProductUnit::Kg, 120, 0],
            ['FLT-SED', 'Sediment Filter', 'filter', ProductType::Consumable, ProductUnit::Pcs, 350, 0],
            ['RO-MEM', 'RO Membrane', 'membrane', ProductType::Consumable, ProductUnit::Pcs, 4500, 0],
            ['PKG-SHRINK', 'Shrink Wrap Roll', 'packaging', ProductType::Consumable, ProductUnit::Roll, 600, 0],
        ];

        foreach ($materials as [$sku, $name, $type, $pType, $unit, $cost, $sale]) {
            Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $cat[$type]->id,
                    'name' => $name,
                    'type' => $pType->value,
                    'unit' => $unit->value,
                    'cost_price' => $cost,
                    'sale_price' => $sale,
                    'reorder_level' => 50,
                    'is_active' => true,
                ],
            );
        }
    }
}
