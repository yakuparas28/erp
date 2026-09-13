<?php

namespace Modules\Fleet\Services;

use App\Models\Tenant;
use Modules\Fleet\Models\FleetTaskSetting;
use Modules\Fleet\Models\VehicleUsageRule;

/**
 * Bir tenant'a Fleet modülü aktif edildiğinde çağrılır; kullanım kuralları
 * ve zamanlanmış görev ayarları için varsayılan satırları oluşturur.
 */
class FleetDefaultsService
{
    public function provision(Tenant $tenant): void
    {
        VehicleUsageRule::forTenant($tenant->id);
        FleetTaskSetting::forTenant($tenant->id);
    }
}
