<?php

namespace Tests\Feature\Hr;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hr\Models\ConsumptionRule;
use Modules\Hr\Models\LeaveHourConfig;
use Modules\Hr\Models\LeaveType;
use Modules\Hr\Services\HrDefaultsService;
use Tests\TestCase;

/**
 * HrDefaultsService::provision, süperadmin bir tenant açtığında
 * TenantProvisioningService içinden zincirli çağrılır. Bu test doğrudan
 * çağırıp beklenen 9 izin türü + 8 tüketim/hakediş kuralı + platform
 * mazeret izni ayarının yerleştiğini doğrular.
 */
class HrDefaultsProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_seeds_the_full_default_catalog(): void
    {
        $tenant = Tenant::factory()->create();

        app(HrDefaultsService::class)->provision($tenant);

        $this->assertSame(9, LeaveType::where('tenant_id', $tenant->id)->count(), 'Default 9 izin türü seed edilmeli');
        $this->assertSame(
            ['dogum', 'evlenme', 'hastalik', 'kismi_ucretli', 'mazeret', 'olum', 'tasinma', 'ucretsiz', 'yillik'],
            LeaveType::where('tenant_id', $tenant->id)->orderBy('key')->pluck('key')->all(),
        );
        $this->assertSame(8, ConsumptionRule::where('tenant_id', $tenant->id)->count(), 'Default 8 kural seed edilmeli');

        $platform = LeaveHourConfig::where('tenant_id', $tenant->id)->whereNull('department_id')->first();
        $this->assertNotNull($platform);
        $this->assertSame('strict', $platform->negative_balance_policy);
        $this->assertTrue((bool) $platform->is_active);
    }

    public function test_provision_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $service = app(HrDefaultsService::class);

        $service->provision($tenant);
        $service->provision($tenant);

        $this->assertSame(9, LeaveType::where('tenant_id', $tenant->id)->count());
        $this->assertSame(8, ConsumptionRule::where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, LeaveHourConfig::where('tenant_id', $tenant->id)->whereNull('department_id')->count());
    }
}
