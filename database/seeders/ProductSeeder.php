<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Enums\ProductType;
use App\Support\Enums\ProductUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The shop's final product catalog (PKR). Idempotent: creates or updates each product by SKU,
 * so it is safe on a fresh or an existing database. Invoices, invoice_items.unit_cost and
 * stock history are never touched.
 *
 * Existing database:  php artisan db:seed --class=ProductSeeder
 *
 * sale_price = own shop / walk-in price · wholesale_price = market price (shop customers)
 * cost_price = NULL means the cost is not configured yet → profit reported as pending.
 */
class ProductSeeder extends Seeder
{
    /** Products no longer sold/stocked. Deactivated (not deleted) — history still points to them. */
    public const RETIRED = [
        'PACK-500-24', 'PACK-6000-6',
        'RAW-WATER', 'LBL-C1', 'LBL-C2',
        'CHEM-CL', 'FLT-SED', 'RO-MEM', 'PKG-SHRINK',
    ];

    public function run(): void
    {
        // 1. Categories
        $categories = [
            'bottled_water' => 'Bottled Water',   // sealed bottles + packs
            'service' => 'Services',              // refills (no stock)
            'finished_goods' => 'Bulk Water',     // produced filtered water (litres)
            'bottle' => 'Bottles',                // 19L bottles + empty bottles you buy
            'cap' => 'Caps',
        ];

        $cat = [];
        foreach ($categories as $type => $name) {
            $cat[$type] = ProductCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'type' => $type, 'is_active' => true],
            );
        }

        // 2. SINGLE bottles — inventory is tracked here, at single-bottle level.
        // [sku, name, cost, own shop price, market price]
        $singles = [
            ['SEAL-500', '500ml Bottle', 27, 50, null],
            ['SEAL-1500', '1.5 Liter Bottle', 37, 100, null],
            ['SEAL-6000', '6 Liter Bottle', 55, 100, 70],
        ];
        $single = [];
        foreach ($singles as [$sku, $name, $cost, $sale, $market]) {
            $single[$sku] = $this->product($sku, [
                'category_id' => $cat['bottled_water']->id,
                'name' => $name,
                'type' => ProductType::Finished->value,
                'unit' => ProductUnit::Pcs->value,
                'unit_type' => 'single',
                'pack_size' => 1,
                'water_litres' => 0,        // sealed goods don't deduct bulk water again
                'cost_price' => $cost,
                'sale_price' => $sale,
                'wholesale_price' => $market,
                'reorder_level' => 100,
                'track_inventory' => true,
            ]);
        }

        // 3. PACKS — no own stock; selling one deducts `pack_size` single bottles.
        // [sku, name, base_sku, qty, cost, price]
        $packs = [
            ['PACK-500-12', '500ml x 12 Pack', 'SEAL-500', 12, 324, 420],         // 12 × 27 · profit 96
            ['PACK-1500-6', '1.5 Liter x 6 Pack (Pat)', 'SEAL-1500', 6, 222, 300], // 6 × 37 · profit 78
        ];
        foreach ($packs as [$sku, $name, $baseSku, $qty, $cost, $sale]) {
            $this->product($sku, [
                'category_id' => $cat['bottled_water']->id,
                'name' => $name,
                'type' => ProductType::Finished->value,
                'unit' => ProductUnit::Pcs->value,
                'unit_type' => 'pack',
                'pack_size' => $qty,
                'pack_of_product_id' => $single[$baseSku]->id,
                'water_litres' => 0,
                'cost_price' => $cost,
                'sale_price' => $sale,
                'wholesale_price' => $sale,   // market (PET) price = same 300 / 420
                'reorder_level' => 0,
                'track_inventory' => false,   // packs hold no stock of their own
            ]);
        }

        // 4. Bulk FILTERED WATER — produced by batches, drawn down by 19L refills (litres).
        $this->product('WATER-BULK', [
            'category_id' => $cat['finished_goods']->id,
            'name' => 'Filtered Water (Bulk)',
            'type' => ProductType::Finished->value,
            'unit' => ProductUnit::Litre->value,
            'water_litres' => 0,
            'cost_price' => 1.5,
            'sale_price' => 0,              // not sold directly
            'reorder_level' => 500,
            'track_inventory' => true,
        ]);

        // 5. 19L REFILL — service; deducts 19 L bulk water. Production cost not confirmed yet.
        $this->product('19L-REFILL', [
            'category_id' => $cat['service']->id,
            'name' => '19 Liter Refill Service',
            'type' => ProductType::Finished->value,
            'is_service' => true,
            'unit' => ProductUnit::Pcs->value,
            'water_litres' => 19,
            'cost_price' => null,
            'sale_price' => 100,
            'track_inventory' => false,
        ]);

        // 6. COOLER REFILL — service; amount typed in per sale (20, 30, …). Cost not configured.
        $this->product('COOLER-REFILL', [
            'category_id' => $cat['service']->id,
            'name' => 'Cooler Refill',
            'type' => ProductType::Finished->value,
            'is_service' => true,
            'is_manual_price' => true,
            'unit' => ProductUnit::Pcs->value,
            'water_litres' => 0,     // cooler sizes vary — no fixed bulk-water deduction
            'cost_price' => null,
            'sale_price' => 0,
            'track_inventory' => false,
        ]);

        // 7. Company 19L EMPTY BOTTLE — the physical bottle stock (cost 1300). Not sold directly.
        $bottle = $this->product('19L-BOTTLE', [
            'category_id' => $cat['bottle']->id,
            'name' => '19L Bottle (Company)',
            'type' => ProductType::Consumable->value,
            'unit' => ProductUnit::Pcs->value,
            'water_litres' => 0,
            'cost_price' => 1300,
            'sale_price' => 0,             // issued on deposit / sold via 19L-NEW below
            'reorder_level' => 20,
            'track_inventory' => true,
        ]);

        // 8. 19L NEW BOTTLE — sold outright at cost (1300 → profit 0); deducts 1 company bottle.
        $this->product('19L-NEW', [
            'category_id' => $cat['bottle']->id,
            'name' => '19L New Bottle',
            'type' => ProductType::Finished->value,
            'unit' => ProductUnit::Pcs->value,
            'unit_type' => 'pack',
            'pack_size' => 1,
            'pack_of_product_id' => $bottle->id,
            'water_litres' => 0,     // bottle only — water is sold as a separate refill line
            'cost_price' => 1300,
            'sale_price' => 1300,
            'reorder_level' => 0,
            'track_inventory' => false, // stock lives on 19L-BOTTLE
        ]);

        // 8b. LOST / BROKEN 19L BOTTLE CHARGE — billed to the customer. Cost = bottle cost,
        //     so charging the full 1300 is profit 0 (the bottle's value comes back).
        $this->product('BOTTLE-CHARGE', [
            'category_id' => $cat['service']->id,
            'name' => '19L Bottle Lost / Broken Charge',
            'type' => ProductType::Finished->value,
            'is_service' => true,
            'is_manual_price' => true,
            'unit' => ProductUnit::Pcs->value,
            'water_litres' => 0,
            'cost_price' => 1300,
            'sale_price' => 0,          // not on the POS grid — charged from the bottle panel
            'track_inventory' => false,
        ]);

        // 9. PURCHASED MATERIALS — empty bottles & caps you buy, then fill in Production.
        //    Cost here is a starting value; each received purchase updates it to the real price.
        // [sku, name, category, cost]
        $materials = [
            ['BTL-500', 'Empty 500ml Bottle', 'bottle', 13],
            ['BTL-1500', 'Empty 1.5L Bottle', 'bottle', 25],
            ['BTL-6000', 'Empty 6L Bottle', 'bottle', 72],
            ['CAP-STD', 'Bottle Cap', 'cap', 0.8],
        ];
        foreach ($materials as [$sku, $name, $type, $cost]) {
            $this->product($sku, [
                'category_id' => $cat[$type]->id,
                'name' => $name,
                'type' => ProductType::Consumable->value,
                'unit' => ProductUnit::Pcs->value,
                'cost_price' => $cost,
                'sale_price' => 0,          // not sold — used up in production
                'reorder_level' => 100,
                'track_inventory' => true,
            ]);
        }

        // 10. Retire products the shop doesn't sell (re-enable any from the Products page).
        Product::whereIn('sku', self::RETIRED)->where('is_active', true)->get()
            ->each(fn (Product $p) => $p->update(['is_active' => false]));
    }

    /** @param array<string, mixed> $fields */
    private function product(string $sku, array $fields): Product
    {
        $product = Product::withTrashed()->firstOrNew(['sku' => $sku]);
        $product->fill($fields + ['is_active' => true]);
        $product->deleted_at = null;
        $product->save();

        return $product;
    }
}
