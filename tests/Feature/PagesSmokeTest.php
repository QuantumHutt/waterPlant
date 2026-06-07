<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Enums\RoleName;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SupplierSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(WarehouseSeeder::class);
        $this->seed(ProductSeeder::class);
        $this->seed(CustomerSeeder::class);
        $this->seed(SupplierSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);
    }

    public static function routes(): array
    {
        return [
            ['dashboard'], ['products.index'], ['inventory.index'], ['production.index'],
            ['gallons.index'], ['purchases.index'], ['sales.index'], ['customers.index'],
            ['suppliers.index'], ['delivery.index'], ['accounting.index'], ['expenses.index'],
            ['employees.index'], ['quality.index'], ['reports.index'], ['users.index'], ['settings.index'],
        ];
    }

    /** @dataProvider routes */
    public function test_module_page_loads(string $routeName): void
    {
        $this->get(route($routeName))->assertOk();
    }
}
