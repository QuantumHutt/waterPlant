<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
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

        // One-time setup expense: the Rs 16,000 filtration cylinder (idempotent).
        $equipment = ExpenseCategory::where('name', 'Equipment')->first();
        $note = 'Filtration Cylinder (initial setup)';

        if ($equipment && ! Expense::where('note', $note)->exists()) {
            app(ExpenseService::class)->create([
                'category_id' => $equipment->id,
                'amount' => 16000,
                'paid_via' => 'cash',
                'vendor' => 'Market',
                'note' => $note,
            ]);
        }
    }
}
