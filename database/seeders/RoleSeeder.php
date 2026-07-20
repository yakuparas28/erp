<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Roller team_id=null ile global tanımlanır; tenant bazlı atama,
     * atama anındaki setPermissionsTeamId bağlamıyla yapılır.
     * Yeni izinler ilgili fazlarda bu seeder'a eklenir.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        setPermissionsTeamId(null);

        Role::findOrCreate('Tenant Admin', 'web');
        Role::findOrCreate('Warehouse Operator', 'web');
        Role::findOrCreate('Purchasing Officer', 'web');

        $this->call(PermissionSeeder::class);
    }
}
