<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'company' => [
                'name' => 'AL-Noor Water Plant & Filter House',
                'brand' => 'BlueRoot',
                'legal_name' => 'AL-Noor Water Plant & Filter House',
                'phone' => '+92 3054662213',
                'email' => 'info@blueroot.pk',
                'address' => 'Industrial Area, Pakistan',
                'logo' => null,
                'ntn' => '',
            ],
            'currency' => [
                'code' => 'PKR',
                'symbol' => 'Rs',
                'position' => 'before',     // before | after
                'decimals' => 2,
                'abbreviation' => true,     // use K / Lac / Crore in summaries
            ],
            'tax' => [
                'enabled' => true,
                'default_rate' => 0,
                'inclusive' => false,
            ],
            'invoice' => [
                'prefix' => 'INV-',
                'next_number' => 1001,
                'terms' => 'Goods once sold will not be returned without prior approval.',
                'footer' => 'Thank you for your business.',
                'show_logo' => true,
            ],
            'production' => [
                'water_product_sku' => 'WATER-BULK', // bulk filtered-water product produced by batches
                'shelf_life_days' => 180,            // auto expiry = production_date + this
            ],
            'sales' => [
                'water_product_sku' => 'WATER-BULK',  // bulk water stock that refills draw down
                'refill_service_sku' => '19L-REFILL', // service product sold on a refill
                'bottle_19l_sku' => '19L-BOTTLE',     // company bottle issued on a new-bottle sale
                'refill_litres' => 19,                // litres per 19L refill / new bottle
                'bottle_deposit' => 1000,             // default refundable security per 19L bottle
            ],
        ];

        foreach ($defaults as $group => $pairs) {
            foreach ($pairs as $key => $value) {
                $type = match (true) {
                    is_bool($value) => 'bool',
                    is_int($value) => 'int',
                    default => 'string',
                };
                Setting::set($group, $key, $value, $type);
            }
        }

        TaxRate::firstOrCreate(
            ['name' => 'GST 0%'],
            ['rate' => 0, 'is_inclusive' => false, 'is_active' => true],
        );
        TaxRate::firstOrCreate(
            ['name' => 'Sales Tax 18%'],
            ['rate' => 18, 'is_inclusive' => false, 'is_active' => true],
        );
    }
}
