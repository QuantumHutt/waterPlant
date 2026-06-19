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
        $categories = [
            ['Electricity', '5100'],
            ['Diesel', '5110'],
            ['Salaries', '5120'],
            ['Maintenance', '5130'],
            ['Transport', '5140'],
            ['Equipment', '5130'],
            ['Labels & Packaging', '5900'],
            ['Miscellaneous', '5900'],
        ];

        foreach ($categories as [$name, $code]) {
            ExpenseCategory::firstOrCreate(
                ['name' => $name],
                ['account_id' => Account::byCode($code)?->id, 'is_active' => true],
            );
        }
    }
}
