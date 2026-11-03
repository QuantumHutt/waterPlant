<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-ready seed: only essential reference/config data.
     * (Demo customers, suppliers, sample stock and demo users are NOT seeded —
     *  enter your real data via the app. Those seeders still exist for tests.)
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,   // 7 roles + permissions
            SuperAdminSeeder::class,       // the super-admin account
            SettingSeeder::class,          // company / currency / tax / invoice settings
            WarehouseSeeder::class,        // default warehouse(s)
            ProductSeeder::class,          // catalog: 500ml / 1.5L / 6L + 19L + packs + raw materials
            ChartOfAccountsSeeder::class,  // double-entry chart of accounts
            ExpenseSetupSeeder::class,     // expense categories
            SalesmanSeeder::class,         // POS salesman account (umer@gmail.com)
        ]);
    }
}
