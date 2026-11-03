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

        // 1. Create every permission — remember which are brand new.
        $newPermissions = [];
        foreach (Permissions::all() as $name) {
            if (Permission::findOrCreate($name, $guard)->wasRecentlyCreated) {
                $newPermissions[] = $name;
            }
        }

        // 2. Create all roles — remember which are brand new.
        $newRoles = [];
        foreach (RoleName::cases() as $role) {
            if (Role::findOrCreate($role->value, $guard)->wasRecentlyCreated) {
                $newRoles[] = $role->value;
            }
        }

        // 3. Super Admin gets all permissions explicitly (also bypasses via Gate::before).
        Role::findByName(RoleName::SuperAdmin->value, $guard)
            ->syncPermissions(Permissions::all());

        // 4. Default matrix. A new role gets its full default set; an existing role keeps the
        //    owner's edits (Roles & Permissions page) and only receives permissions that are new.
        foreach (Permissions::roleMatrix() as $roleName => $permissions) {
            $role = Role::findByName($roleName, $guard);
            if (in_array($roleName, $newRoles, true)) {
                $role->syncPermissions($permissions);
            } elseif ($add = array_values(array_intersect($permissions, $newPermissions))) {
                $role->givePermissionTo($add);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
