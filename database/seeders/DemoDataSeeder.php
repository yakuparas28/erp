<?php

namespace Database\Seeders;

use App\Models\LicensePackage;
use App\Models\Module;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Platform\ModuleActivationService;
use Illuminate\Database\Seeder;

/**
 * Geliştirme/test ortamı için örnek veriler. Üretimde çalıştırılmaz.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'company' => [
                    'name' => 'Acme Lojistik AŞ',
                    'accounting_mode' => 'continental',
                    'tax_number' => '1234567801',
                    'tax_office' => 'Kadıköy',
                    'email' => 'info@acmelojistik.test',
                    'phone' => '0216 555 01 01',
                    'address' => 'Ataşehir, İstanbul',
                ],
                'admin' => ['name' => 'Ali Kaya', 'email' => 'ali@acmelojistik.test'],
                'package' => 'Premium',
                'status' => 'active',
                'ends_at' => now()->addYear()->toDateString(),
            ],
            [
                'company' => [
                    'name' => 'Demo Ticaret Ltd',
                    'accounting_mode' => 'continental',
                    'tax_number' => '9876543202',
                    'tax_office' => 'Çankaya',
                    'email' => 'info@demoticaret.test',
                    'phone' => '0312 555 02 02',
                    'address' => 'Çankaya, Ankara',
                ],
                'admin' => ['name' => 'Zeynep Demir', 'email' => 'zeynep@demoticaret.test'],
                'package' => 'Standart',
                'status' => 'trial',
                'ends_at' => now()->addDays(14)->toDateString(),
            ],
            [
                'company' => [
                    'name' => 'Test Gıda Sanayi AŞ',
                    'accounting_mode' => 'anglo_saxon',
                    'tax_number' => '4567891203',
                    'tax_office' => 'Konak',
                    'email' => 'info@testgida.test',
                    'phone' => '0232 555 03 03',
                    'address' => 'Konak, İzmir',
                ],
                'admin' => ['name' => 'Mehmet Yılmaz', 'email' => 'mehmet@testgida.test'],
                'package' => 'Başlangıç',
                'status' => 'active',
                'ends_at' => null,
            ],
        ];

        foreach ($tenants as $definition) {
            $tenant = Tenant::firstOrCreate(
                ['name' => $definition['company']['name']],
                $definition['company'],
            );

            $adminUser = User::firstOrNew(['email' => $definition['admin']['email']]);
            $adminUser->fill(['name' => $definition['admin']['name'], 'password' => 'password']);
            $adminUser->tenant_id = $tenant->id;
            $adminUser->save();

            setPermissionsTeamId($tenant->id);
            $adminUser->assignRole('Tenant Admin');

            TenantSubscription::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'license_package_id' => LicensePackage::where('name', $definition['package'])->firstOrFail()->id,
                    'status' => $definition['status'],
                    'starts_at' => now()->toDateString(),
                    'ends_at' => $definition['ends_at'],
                ],
            );
        }

        // Başlangıç paketli tenant'a manuel eklenti örneği: Muhasebe modülü.
        app(ModuleActivationService::class)->activateManually(
            Tenant::where('name', 'Test Gıda Sanayi AŞ')->firstOrFail(),
            Module::where('key', 'accounting')->firstOrFail(),
            SuperAdmin::firstOrFail(),
        );
    }
}
