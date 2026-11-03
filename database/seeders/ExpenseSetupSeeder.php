<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseSetupSeeder extends Seeder
{
    public function run(): void
    {
        // Expense categories mapped to chart-of-accounts expense heads.
        // A–Z running costs of the shop/plant. Buying stock to sell (bottles, packs) is a
        // PURCHASE, not an expense — its cost is counted when sold (cost of goods).
        $categories = [
            ['Bank Charges', '5190'],
            ['Chemicals (Chlorine etc.)', '5170'],
            ['Cleaning & Sanitation', '5170'],
            ['Daily Wages / Labour', '5120'],
            ['Delivery Vehicle Fuel', '5200'],
            ['Delivery Vehicle Repair', '5200'],
            ['Diesel', '5110'],                      // generator
            ['Electricity', '5100'],
            ['Equipment', '5130'],                   // repairs / small tools
            ['Filters & RO Membranes', '5170'],
            ['Gas Bill', '5160'],
            ['Government Fees & Taxes', '5190'],
            ['Internet & Mobile', '5160'],
            ['Labels & Packaging', '5900'],
            ['Maintenance', '5130'],                 // plant / machine repair
            ['Marketing & Printing', '5180'],
            ['Miscellaneous', '5900'],
            ['Refreshments (Tea / Food)', '5900'],
            ['Rent', '5150'],
            ['Salaries', '5120'],                    // enter here when salaries are paid
            ['Stationery & Receipt Rolls', '5900'],
            ['Transport', '5140'],
            ['Water Testing (Lab)', '5130'],
            ['Water Bill', '5160'],
        ];

        foreach ($categories as [$name, $code]) {
            ExpenseCategory::firstOrCreate(
                ['name' => $name],
                ['account_id' => Account::byCode($code)?->id, 'is_active' => true],
            );
        }
    }
}
