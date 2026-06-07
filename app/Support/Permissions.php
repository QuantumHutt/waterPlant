<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Enums\RoleName;

/**
 * Central catalogue of permissions and the default role → permission matrix.
 * Consumed by RolePermissionSeeder and available app-wide for reference.
 */
final class Permissions
{
    /**
     * group => list of actions. Permission name = "{group}.{action}".
     *
     * @var array<string, list<string>>
     */
    public const GROUPS = [
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'settings' => ['view', 'update'],
        'products' => ['view', 'create', 'update', 'delete'],
        'inventory' => ['view', 'create', 'update', 'delete', 'adjust', 'transfer', 'audit'],
        'production' => ['view', 'create', 'update', 'delete'],
        'gallons' => ['view', 'manage'],
        'customers' => ['view', 'create', 'update', 'delete'],
        'suppliers' => ['view', 'create', 'update', 'delete'],
        'purchases' => ['view', 'create', 'update', 'delete', 'receive'],
        'sales' => ['view', 'create', 'update', 'delete', 'return'],
        'delivery' => ['view', 'create', 'update', 'delete', 'confirm'],
        'accounting' => ['view', 'create', 'update', 'delete', 'post'],
        'expenses' => ['view', 'create', 'update', 'delete'],
        'employees' => ['view', 'create', 'update', 'delete'],
        'payroll' => ['view', 'process'],
        'quality' => ['view', 'create', 'update', 'delete'],
        'reports' => ['view'],
        'audit' => ['view'],
        'notifications' => ['view'],
    ];

    /** @return list<string> Flat list of every permission name. */
    public static function all(): array
    {
        $permissions = [];
        foreach (self::GROUPS as $group => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$group}.{$action}";
            }
        }

        return $permissions;
    }

    /** @param list<string> $groups @return list<string> */
    public static function forGroups(array $groups): array
    {
        $permissions = [];
        foreach ($groups as $group) {
            foreach (self::GROUPS[$group] ?? [] as $action) {
                $permissions[] = "{$group}.{$action}";
            }
        }

        return $permissions;
    }

    /** @param list<string> $groups @return list<string> only the *.view permission of each group */
    public static function viewOnly(array $groups): array
    {
        return array_map(fn (string $g) => "{$g}.view", $groups);
    }

    /**
     * Default permission set per role. super-admin is omitted — it bypasses
     * all checks via Gate::before.
     *
     * @return array<string, list<string>>
     */
    public static function roleMatrix(): array
    {
        return [
            RoleName::Admin->value => self::all(),

            RoleName::Manager->value => array_merge(
                self::viewOnly(['users', 'roles', 'settings', 'audit']),
                self::forGroups([
                    'products', 'inventory', 'production', 'gallons',
                    'customers', 'suppliers', 'purchases', 'sales', 'delivery',
                    'employees', 'quality',
                ]),
                self::viewOnly(['accounting', 'expenses', 'payroll']),
                ['reports.view', 'notifications.view'],
            ),

            RoleName::Accountant->value => array_merge(
                self::forGroups(['accounting', 'expenses', 'suppliers', 'purchases', 'payroll']),
                self::viewOnly(['products', 'inventory', 'customers', 'sales', 'employees']),
                ['settings.view', 'settings.update', 'reports.view', 'audit.view', 'notifications.view'],
            ),

            RoleName::ProductionManager->value => array_merge(
                self::forGroups(['production', 'quality']),
                self::viewOnly(['products', 'inventory']),
                ['inventory.adjust', 'reports.view', 'notifications.view'],
            ),

            RoleName::SalesManager->value => array_merge(
                self::forGroups(['sales', 'customers', 'gallons']),
                self::viewOnly(['products', 'inventory', 'delivery']),
                ['reports.view', 'notifications.view'],
            ),

            RoleName::DeliveryStaff->value => array_merge(
                ['delivery.view', 'delivery.confirm'],
                self::viewOnly(['customers', 'sales']),
                ['notifications.view'],
            ),
        ];
    }
}
