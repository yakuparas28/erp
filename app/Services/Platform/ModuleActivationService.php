<?php

namespace App\Services\Platform;

use App\Models\Module;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ModuleActivationService
{
    /**
     * Abonelik paketindeki modülleri source=package olarak aktive eder,
     * paketten çıkan source=package kayıtlarını pasifleştirir.
     * source=manual_addon / trial kayıtlarına dokunmaz (PRD 3.16).
     */
    public function syncFromPackage(TenantSubscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $packageModuleIds = $subscription->licensePackage()
                ->firstOrFail()
                ->modules()
                ->pluck('modules.id');

            foreach ($packageModuleIds as $moduleId) {
                TenantModuleActivation::updateOrCreate(
                    ['tenant_id' => $subscription->tenant_id, 'module_id' => $moduleId],
                    [
                        'is_active' => true,
                        'source' => 'package',
                        'activated_at' => now(),
                        'deactivated_at' => null,
                    ],
                );
            }

            TenantModuleActivation::where('tenant_id', $subscription->tenant_id)
                ->where('source', 'package')
                ->whereNotIn('module_id', $packageModuleIds)
                ->where('is_active', true)
                ->get()
                ->each(fn (TenantModuleActivation $activation) => $activation->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                ]));
        });
    }

    /**
     * Paketten bağımsız, tenant'a özel istisnai aktivasyon (PRD 3.16).
     */
    public function activateManually(Tenant $tenant, Module $module, SuperAdmin $superAdmin): TenantModuleActivation
    {
        return TenantModuleActivation::updateOrCreate(
            ['tenant_id' => $tenant->id, 'module_id' => $module->id],
            [
                'is_active' => true,
                'source' => 'manual_addon',
                'activated_by_super_admin_id' => $superAdmin->id,
                'activated_at' => now(),
                'deactivated_at' => null,
            ],
        );
    }

    public function deactivate(Tenant $tenant, Module $module, SuperAdmin $superAdmin): void
    {
        if ($module->is_core) {
            throw new InvalidArgumentException("Çekirdek modül ({$module->key}) hiçbir tenant için kapatılamaz.");
        }

        TenantModuleActivation::where('tenant_id', $tenant->id)
            ->where('module_id', $module->id)
            ->get()
            ->each(fn (TenantModuleActivation $activation) => $activation->update([
                'is_active' => false,
                'activated_by_super_admin_id' => $superAdmin->id,
                'deactivated_at' => now(),
            ]));
    }
}
