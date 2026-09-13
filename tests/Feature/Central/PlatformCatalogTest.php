<?php

namespace Tests\Feature\Central;

use App\Models\LicensePackage;
use App\Models\Module;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class]);
    }

    public function test_all_erp_modules_are_seeded_and_only_inventory_is_core(): void
    {
        $this->assertSame(
            ['accounting', 'fleet', 'hr', 'inventory', 'purchase', 'sales'],
            Module::orderBy('key')->pluck('key')->all(),
        );

        $this->assertSame(
            ['inventory'],
            Module::where('is_core', true)->pluck('key')->all(),
        );
    }

    public function test_license_packages_map_to_correct_module_sets(): void
    {
        $expected = [
            'Başlangıç' => ['inventory'],
            'Standart' => ['fleet', 'hr', 'inventory', 'purchase', 'sales'],
            'Premium' => ['accounting', 'fleet', 'hr', 'inventory', 'purchase', 'sales'],
        ];

        foreach ($expected as $packageName => $moduleKeys) {
            $package = LicensePackage::where('name', $packageName)->firstOrFail();

            $this->assertSame(
                $moduleKeys,
                $package->modules()->orderBy('key')->pluck('key')->all(),
                "{$packageName} paketi yanlış modül setine sahip",
            );
        }
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class]);

        $this->assertSame(6, Module::count());
        $this->assertSame(3, LicensePackage::count());
    }
}
