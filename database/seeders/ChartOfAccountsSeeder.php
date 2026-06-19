<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // [code, name, type, sub_type]
            ['1000', 'Cash in Hand', 'asset', 'current_asset'],
            ['1010', 'Bank Account', 'asset', 'current_asset'],
            ['1100', 'Accounts Receivable', 'asset', 'current_asset'],
            ['1200', 'Inventory', 'asset', 'current_asset'],
            ['1500', 'Plant & Equipment', 'asset', 'fixed_asset'],
            ['2000', 'Accounts Payable', 'liability', 'current_liability'],
            ['2100', 'Customer Gallon Deposits', 'liability', 'current_liability'],
            ['3000', 'Owner Capital', 'equity', null],
            ['3900', 'Retained Earnings', 'equity', null],
            ['4000', 'Sales Revenue', 'income', null],
            ['4900', 'Other Income', 'income', null],
            ['5000', 'Cost of Goods Sold', 'expense', null],
            ['5100', 'Electricity', 'expense', null],
            ['5110', 'Diesel', 'expense', null],
            ['5120', 'Salaries', 'expense', null],
            ['5130', 'Maintenance', 'expense', null],
            ['5140', 'Transport', 'expense', null],
            ['5900', 'Miscellaneous', 'expense', null],
        ];

        foreach ($accounts as [$code, $name, $type, $sub]) {
            Account::firstOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'sub_type' => $sub, 'is_active' => true,
            ]);
        }
        // Expense categories are seeded in ExpenseSetupSeeder (single source).
    }
}
