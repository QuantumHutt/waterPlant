<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Accountant = 'accountant';
    case ProductionManager = 'production-manager';
    case SalesManager = 'sales-manager';
    case DeliveryStaff = 'delivery-staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Accountant => 'Accountant',
            self::ProductionManager => 'Production Manager',
            self::SalesManager => 'Sales Manager',
            self::DeliveryStaff => 'Delivery Staff',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
