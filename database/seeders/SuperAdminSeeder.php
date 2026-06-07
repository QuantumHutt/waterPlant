<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\Enums\RoleName;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Standalone, idempotent Super Admin bootstrap.
 *
 * Usage:
 *   php artisan db:seed --class=Database\\Seeders\\SuperAdminSeeder
 *
 * Credentials can be overridden via env:
 *   SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Ensure permissions + the super-admin role exist even if run on its own.
        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $role = Role::findOrCreate(RoleName::SuperAdmin->value, 'web');
        $role->syncPermissions(Permissions::all());

        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@waterplant.test');

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'is_active' => true,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ],
        );

        $user->syncRoles(RoleName::SuperAdmin->value);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info("Super Admin ready: {$email}");
    }
}
