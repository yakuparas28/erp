<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ModuleSeeder::class,
            LicensePackageSeeder::class,
            RoleSeeder::class,
        ]);

        SuperAdmin::firstOrCreate(
            ['email' => 'admin@erp.test'],
            ['name' => 'Platform Yöneticisi', 'password' => 'password'],
        );
    }
}
