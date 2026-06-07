<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Enums\RoleName;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    public function test_defaults_are_seeded(): void
    {
        $this->assertSame('PKR', Setting::get('currency', 'code'));
        $this->assertSame('Rs', Setting::get('currency', 'symbol'));
        $this->assertSame('INV-', Setting::get('invoice', 'prefix'));
    }

    public function test_admin_can_view_settings_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->get('/settings')
            ->assertOk();
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->put('/settings', [
                'company' => ['name' => 'Aqua Pure Plant'],
                'currency' => ['symbol' => 'Rs', 'code' => 'PKR'],
            ])
            ->assertRedirect();

        $this->assertSame('Aqua Pure Plant', Setting::get('company', 'name'));
    }

    public function test_production_manager_cannot_update_settings(): void
    {
        $pm = User::factory()->create();
        $pm->assignRole(RoleName::ProductionManager->value);

        $this->actingAs($pm)
            ->put('/settings', ['company' => ['name' => 'Hacked']])
            ->assertForbidden();
    }
}
