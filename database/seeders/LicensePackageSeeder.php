<?php

namespace Database\Seeders;

use App\Models\LicensePackage;
use App\Models\Module;
use Illuminate\Database\Seeder;

class LicensePackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            'Başlangıç' => ['monthly_price' => 499, 'modules' => ['inventory']],
            'Standart' => ['monthly_price' => 999, 'modules' => ['inventory', 'sales', 'purchase']],
            'Premium' => ['monthly_price' => 1999, 'modules' => ['inventory', 'sales', 'purchase', 'accounting']],
        ];

        foreach ($packages as $name => $definition) {
            $package = LicensePackage::updateOrCreate(
                ['name' => $name],
                ['monthly_price' => $definition['monthly_price']],
            );

            $package->modules()->sync(
                Module::whereIn('key', $definition['modules'])->pluck('id'),
            );
        }
    }
}
