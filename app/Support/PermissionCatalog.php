<?php

namespace App\Support;

/**
 * Modül anahtarına göre gruplu izin kataloğu (PRD Bölüm 2).
 * Her faz kendi izinlerini buraya ekler; PermissionSeeder bu katalogdan
 * üretir, rol/izin ekranı tenant'ın aktif modüllerine göre filtreler.
 */
final class PermissionCatalog
{
    /** Modül bağımsız, her tenant'ta görünen grup. */
    public const CORE_GROUP = 'core';

    /**
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'core' => [
                'manage users',
                'manage roles',
                'manage settings',
                'manage partners',
            ],
            'inventory' => [
                'view stock',
                'manage warehouses',
                'manage products',
                'perform stock counts',
                'approve inventory adjustments',
                'manage warehouse transfers',
                'approve landed costs',
                'manage routes',
                'manage reordering rules',
                'perform scrap operations',
            ],
            'sales' => [
                'create sales orders',
                'confirm sales orders',
            ],
            'purchase' => [
                'create purchase orders',
                'confirm purchase orders',
            ],
            'accounting' => [
                'manage chart of accounts',
                'post journal entries',
                'register payments',
            ],
            'hr' => [
                'submit own leave',
                'manage employees',
                'manage departments',
                'approve leave first level',
                'approve leave second level',
                'view leave monitoring',
                'manage leave balances',
                'manage leave configuration',
                'manage consumption rules',
            ],
            'fleet' => [
                'view fleet calendar',
                'reserve vehicle',
                'view own reservations',
                'confirm own pickup',
                'submit own delivery',
                'manage vehicles',
                'approve vehicle reservation',
                'confirm vehicle delivery',
                'manage vehicle calendar',
                'manage usage rules',
                'view usage report',
                'export usage report',
                'view fleet dashboard',
                'manage fleet task settings',
                'run critical window',
                'manage maintenance records',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    /**
     * @return list<string>
     */
    public static function warehouseOperatorDefaults(): array
    {
        return ['view stock', 'perform stock counts'];
    }

    /**
     * @return list<string>
     */
    public static function purchasingOfficerDefaults(): array
    {
        return ['create purchase orders', 'manage partners'];
    }

    /**
     * @return list<string>
     */
    public static function salesRepresentativeDefaults(): array
    {
        return ['create sales orders', 'manage partners'];
    }

    /**
     * @return list<string>
     */
    public static function accountantDefaults(): array
    {
        return ['manage chart of accounts', 'post journal entries', 'register payments'];
    }

    /**
     * @return list<string>
     */
    public static function employeeDefaults(): array
    {
        return [
            'submit own leave',
            'view fleet calendar',
            'reserve vehicle',
            'view own reservations',
            'confirm own pickup',
            'submit own delivery',
        ];
    }

    /** @return list<string> */
    public static function fleetManagerDefaults(): array
    {
        return [
            'view fleet calendar',
            'view fleet dashboard',
            'reserve vehicle',
            'view own reservations',
            'confirm own pickup',
            'submit own delivery',
            'manage vehicles',
            'approve vehicle reservation',
            'confirm vehicle delivery',
            'manage vehicle calendar',
            'manage usage rules',
            'view usage report',
            'export usage report',
            'manage fleet task settings',
            'run critical window',
            'manage maintenance records',
        ];
    }
}
