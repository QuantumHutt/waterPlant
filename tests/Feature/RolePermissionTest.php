<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Enums\RoleName;
use App\Support\Permissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_all_roles_and_permissions_are_seeded(): void
    {
        $this->assertCount(7, Role::all());
        $this->assertSame(count(Permissions::all()), Permission::count());
    }

    public function test_super_admin_bypasses_every_ability(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        $this->assertTrue($user->can('accounting.post'));
        $this->assertTrue($user->can('users.delete'));
        $this->assertTrue($user->can('a.non.existent.ability.still.passes'));
    }

    public function test_admin_has_full_catalogue(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        $this->assertTrue($user->can('users.create'));
        $this->assertTrue($user->can('accounting.post'));
    }

    public function test_delivery_staff_is_restricted(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::DeliveryStaff->value);

        $this->assertTrue($user->can('delivery.confirm'));
        $this->assertFalse($user->can('sales.create'));
        $this->assertFalse($user->can('users.view'));
    }

    public function test_accountant_owns_accounting_but_not_production(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Accountant->value);

        $this->assertTrue($user->can('accounting.post'));
        $this->assertTrue($user->can('expenses.create'));
        $this->assertFalse($user->can('production.create'));
    }
}
