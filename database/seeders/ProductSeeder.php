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

        // 2. SINGLE bottled-water products — inventory is tracked here, at single-bottle level.
        // [sku, name, cost, sale]
        $singles = [
            ['SEAL-500', '500ml Bottle', 13, 30],     // empty bottle cost Rs 13
            ['SEAL-1500', '1.5 Liter Bottle', 25, 70], // empty bottle cost Rs 25
            ['SEAL-6000', '6 Liter Bottle', 72, 150],  // empty bottle cost Rs 72
        ];
        $single = [];
        foreach ($singles as [$sku, $name, $cost, $sale]) {
            $single[$sku] = Product::firstOrCreate(['sku' => $sku], [
                'category_id' => $cat['bottled_water']->id,
                'name' => $name,
                'type' => ProductType::Finished->value,
                'unit' => ProductUnit::Pcs->value,
                'unit_type' => 'single',
                'pack_size' => 1,
                'water_litres' => 0,        // sealed goods don't deduct bulk water again
                'cost_price' => $cost,
                'sale_price' => $sale,
                'reorder_level' => 100,
                'track_inventory' => true,
                'is_active' => true,
            ]);
        }

        // 2a. PACK / PAT products — NO own stock; selling one deducts `pack_size` base bottles.
        //     Pack price = single price × qty with a small wholesale discount.
        // [sku, name, base_sku, qty]
        $packs = [
            ['PACK-500-12', '500ml x 12 Pack', 'SEAL-500', 12],
            ['PACK-500-24', '500ml x 24 Pack', 'SEAL-500', 24],
            ['PACK-1500-6', '1.5 Liter x 6 Pack (Pat)', 'SEAL-1500', 6],
            ['PACK-6000-6', '6 Liter x 6 Pack', 'SEAL-6000', 6],
        ];
        foreach ($packs as [$sku, $name, $baseSku, $qty]) {
            $base = $single[$baseSku];
            Product::firstOrCreate(['sku' => $sku], [
                'category_id' => $cat['bottled_water']->id,
                'name' => $name,
                'type' => ProductType::Finished->value,
                'unit' => ProductUnit::Pcs->value,
                'unit_type' => 'pack',
                'pack_size' => $qty,
                'pack_of_product_id' => $base->id,
                'water_litres' => 0,
                'cost_price' => round((float) $base->cost_price * $qty, 2),
                'sale_price' => round((float) $base->sale_price * $qty * 0.95, 0), // ~5% pack discount
                'reorder_level' => 0,
                'track_inventory' => false,   // packs hold no stock of their own
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
            'cost_price' => 1250,   // empty 19L gallon bottle cost
            'sale_price' => 0,             // not sold — issued on refundable deposit
            'reorder_level' => 20,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        // 3. Raw / consumable materials.
        $materials = [
            ['RAW-WATER', 'Raw Tap Water', 'raw', ProductType::Raw, ProductUnit::Litre, 0.05, 0],
            ['BTL-500', 'Empty 500ml Bottle', 'bottle', ProductType::Consumable, ProductUnit::Pcs, 13, 0],
            ['BTL-1500', 'Empty 1.5L Bottle', 'bottle', ProductType::Consumable, ProductUnit::Pcs, 25, 0],
            ['BTL-6000', 'Empty 6L Bottle', 'bottle', ProductType::Consumable, ProductUnit::Pcs, 72, 0],
            ['CAP-STD', 'Bottle Cap', 'cap', ProductType::Consumable, ProductUnit::Pcs, 0.8, 0],
            ['LBL-C1', 'Label - Color 1', 'label', ProductType::Consumable, ProductUnit::Pcs, 2, 0],
            ['LBL-C2', 'Label - Color 2', 'label', ProductType::Consumable, ProductUnit::Pcs, 2, 0],
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
