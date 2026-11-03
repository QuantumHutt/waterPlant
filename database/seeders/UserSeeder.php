<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Super Admin', 'superadmin@waterplant.test', RoleName::SuperAdmin],
            ['Admin User', 'admin@waterplant.test', RoleName::Admin],
            ['Branch Manager', 'manager@waterplant.test', RoleName::Manager],
            ['Accountant', 'accountant@waterplant.test', RoleName::Accountant],
            ['Production Manager', 'production@waterplant.test', RoleName::ProductionManager],
            ['Sales Manager', 'sales@waterplant.test', RoleName::SalesManager],
            ['Delivery Staff', 'delivery@waterplant.test', RoleName::DeliveryStaff],
            ['Salesman', 'salesman@waterplant.test', RoleName::Salesman],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles($role->value);
        }
    }
}
