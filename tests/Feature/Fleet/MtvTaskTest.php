<?php

namespace Tests\Feature\Fleet;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Modules\Fleet\Models\FleetTaskSetting;
use Modules\Fleet\Models\Vehicle;
use Tests\TenantTestCase;

class MtvTaskTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 07:05'));

        $fm = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $fm->assignRole('Fleet Manager');
    }

    public function test_overdue_mtv_marks_uyari_son_gonderim(): void
    {
        $v = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'mtv_odeme_tarihi' => now()->subDays(5)->toDateString(),
            'mtv_odeme_durumu' => Vehicle::MTV_ODENMEDI,
            'mtv_uyari_son_gonderim' => null,
        ]);
        FleetTaskSetting::forTenant($this->tenant->id);

        Artisan::call('fleet:check-mtv', ['--tenant' => $this->tenant->id]);

        $this->assertSame(today()->toDateString(), $v->fresh()->mtv_uyari_son_gonderim?->toDateString());
    }

    public function test_upcoming_mtv_within_window_is_flagged(): void
    {
        FleetTaskSetting::forTenant($this->tenant->id)->update(['mtv_reminder_days' => 30]);
        $v = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'mtv_odeme_tarihi' => now()->addDays(20)->toDateString(),
            'mtv_odeme_durumu' => Vehicle::MTV_ODENMEDI,
            'mtv_uyari_son_gonderim' => null,
        ]);

        Artisan::call('fleet:check-mtv', ['--tenant' => $this->tenant->id]);

        $this->assertSame(today()->toDateString(), $v->fresh()->mtv_uyari_son_gonderim?->toDateString());
    }

    public function test_paid_mtv_is_skipped(): void
    {
        $v = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'mtv_odeme_tarihi' => now()->addDays(10)->toDateString(),
            'mtv_odeme_durumu' => Vehicle::MTV_ODENDI,
            'mtv_uyari_son_gonderim' => null,
        ]);

        Artisan::call('fleet:check-mtv', ['--tenant' => $this->tenant->id]);

        $this->assertNull($v->fresh()->mtv_uyari_son_gonderim);
    }

    public function test_same_day_second_run_is_idempotent(): void
    {
        $today = today()->toDateString();
        $v = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'mtv_odeme_tarihi' => now()->subDays(3)->toDateString(),
            'mtv_odeme_durumu' => Vehicle::MTV_ODENMEDI,
            'mtv_uyari_son_gonderim' => $today,
        ]);

        Artisan::call('fleet:check-mtv', ['--tenant' => $this->tenant->id]);

        $this->assertSame($today, $v->fresh()->mtv_uyari_son_gonderim?->toDateString());
    }
}
