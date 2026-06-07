<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Enums\RoleName;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';

        // 1. Create every permission.
        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, $guard);
        }

        // 2. Create all roles.
        foreach (RoleName::cases() as $role) {
            Role::findOrCreate($role->value, $guard);
        }

        // 3. Super Admin gets all permissions explicitly (also bypasses via Gate::before).
        Role::findByName(RoleName::SuperAdmin->value, $guard)
            ->syncPermissions(Permissions::all());

        // 4. Assign the default matrix to the remaining roles.
        foreach (Permissions::roleMatrix() as $roleName => $permissions) {
            Role::findByName($roleName, $guard)->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
