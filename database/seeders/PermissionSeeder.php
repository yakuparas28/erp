<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        setPermissionsTeamId(null);

        Role::findByName('Tenant Admin', 'web')->syncPermissions(PermissionCatalog::all());
        Role::findByName('Warehouse Operator', 'web')->syncPermissions(PermissionCatalog::warehouseOperatorDefaults());
        Role::findByName('Purchasing Officer', 'web')->syncPermissions(PermissionCatalog::purchasingOfficerDefaults());
        Role::findByName('Sales Representative', 'web')->syncPermissions(PermissionCatalog::salesRepresentativeDefaults());
        Role::findByName('Accountant', 'web')->syncPermissions(PermissionCatalog::accountantDefaults());
    }
}
