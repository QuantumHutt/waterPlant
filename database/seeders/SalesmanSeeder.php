<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Idempotent POS salesman account (role: salesman — POS only).
 *
 * Usage:
 *   php artisan db:seed --class=RolePermissionSeeder   # creates the salesman role + permissions
 *   php artisan db:seed --class=SalesmanSeeder
 *
 * Env overrides: SALESMAN_NAME, SALESMAN_EMAIL, SALESMAN_PASSWORD (default "password").
 * Re-running resets the password to SALESMAN_PASSWORD / the default — change it after first login.
 */
class SalesmanSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SALESMAN_EMAIL', 'umer@gmail.com');
        $password = env('SALESMAN_PASSWORD','blueroot123');
        $user = User::withTrashed()->where('email', $email)->first();

        $generated = null;
        if (! $password && ! $user) {
            $password = $generated = Str::password(12, symbols: false);
        }

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            array_filter([
                'name' => env('SALESMAN_NAME', 'Umer'),
                'password' => $password ? Hash::make($password) : null,
                'is_active' => true,
                'email_verified_at' => now(),
            ], fn ($v) => $v !== null) + ['deleted_at' => null],
        );

        $user->syncRoles(RoleName::Salesman->value);

        $this->command?->info("Salesman ready: {$email}".($generated ? " — password: {$generated} (change after first login)" : ''));
    }
}
