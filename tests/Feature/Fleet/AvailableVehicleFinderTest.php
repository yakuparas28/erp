<?php

namespace Tests\Feature\Fleet;

use Carbon\CarbonImmutable;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCalendarBlock;
use Modules\Fleet\Services\AvailableVehicleFinder;
use Tests\TenantTestCase;

class AvailableVehicleFinderTest extends TenantTestCase
{
    private CarbonImmutable $pickup;

    private CarbonImmutable $return;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenantUser($this->tenant, 'Tenant Admin');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 08:00'));
        $this->pickup = CarbonImmutable::parse('2026-10-15 09:00');
        $this->return = CarbonImmutable::parse('2026-10-17 18:00');
    }

    public function test_free_vehicle_is_returned(): void
    {
        $v = Vehicle::factory()->create(['tenant_id' => $this->tenant->id, 'durum' => Vehicle::DURUM_GARAJDA]);

        $result = app(AvailableVehicleFinder::class)->available($this->pickup, $this->return);

        $this->assertTrue($result->contains(fn ($x) => $x->id === $v->id));
    }

    public function test_vehicle_with_overlapping_calendar_block_is_hidden(): void
    {
        $v = Vehicle::factory()->create(['tenant_id' => $this->tenant->id, 'durum' => Vehicle::DURUM_GARAJDA]);
        VehicleCalendarBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'vehicle_id' => $v->id,
            'block_type' => VehicleCalendarBlock::TYPE_BAKIM,
            'start_date' => '2026-10-16',
            'end_date' => '2026-10-16',
        ]);

        $result = app(AvailableVehicleFinder::class)->available($this->pickup, $this->return);

        $this->assertFalse($result->contains(fn ($x) => $x->id === $v->id));
    }

    public function test_vehicle_with_active_reservation_overlap_is_hidden(): void
    {
        $v = Vehicle::factory()->create(['tenant_id' => $this->tenant->id, 'durum' => Vehicle::DURUM_GARAJDA]);
        Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'arac_id' => $v->id,
            'aktif_sofor_id' => $this->tenantAdmin->id,
            'onay_durumu' => Reservation::ONAY_ONAYLANDI,
            'planlanan_alis_at' => '2026-10-16 08:00',
            'planlanan_teslim_at' => '2026-10-16 20:00',
            'teslim_tarihi' => null,
        ]);

        $result = app(AvailableVehicleFinder::class)->available($this->pickup, $this->return);

        $this->assertFalse($result->contains(fn ($x) => $x->id === $v->id));
    }

    public function test_vehicle_fails_business_day_buffer_before_next_block(): void
    {
        $v = Vehicle::factory()->create(['tenant_id' => $this->tenant->id, 'durum' => Vehicle::DURUM_GARAJDA]);
        // Teslim 2026-10-17 (Cumartesi). Sonraki bakım 2026-10-19 (Pazartesi).
        // addWeekday(2026-10-17) = 2026-10-19; başlangıç == addWeekday olduğu için 1 iş günü kalmıyor → hariç tut.
        VehicleCalendarBlock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'vehicle_id' => $v->id,
            'block_type' => VehicleCalendarBlock::TYPE_BAKIM,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-19',
        ]);

        $result = app(AvailableVehicleFinder::class)->available($this->pickup, $this->return);

        $this->assertFalse($result->contains(fn ($x) => $x->id === $v->id));
    }

    public function test_blocked_vehicle_is_hidden_regardless(): void
    {
        $v = Vehicle::factory()->create(['tenant_id' => $this->tenant->id, 'durum' => Vehicle::DURUM_BLOKELI]);

        $result = app(AvailableVehicleFinder::class)->available($this->pickup, $this->return);

        $this->assertFalse($result->contains(fn ($x) => $x->id === $v->id));
    }
}
