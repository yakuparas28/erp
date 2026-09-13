<?php

namespace Tests\Feature\Fleet;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Tests\TenantTestCase;

class ReservationFlowTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 08:00'));
        Storage::fake('public');
    }

    public function test_end_to_end_reserve_approve_pickup_deliver_confirm(): void
    {
        $employee = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $employee->assignRole('Employee');

        $fleetManager = User::factory()->for($this->tenant)->create();
        $fleetManager->assignRole('Fleet Manager');

        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'durum' => Vehicle::DURUM_GARAJDA,
            'guncel_km' => 10_000,
        ]);

        // 1) Personel talep açar
        $this->actingAs($employee);
        $this->post(route('app.fleet.reservations.store'), [
            'arac_id' => $vehicle->id,
            'planlanan_alis_at' => '2026-10-15 09:00',
            'planlanan_teslim_at' => '2026-10-15 18:00',
        ])->assertRedirect(route('app.fleet.reservations.index'));

        $reservation = Reservation::first();
        $this->assertSame(Reservation::ONAY_BEKLEMEDE, $reservation->onay_durumu);
        $this->assertSame($employee->id, $reservation->aktif_sofor_id);

        // 2) Filo Yöneticisi onaylar
        $this->actingAs($fleetManager);
        $this->post(route('app.fleet.approvals.approve', $reservation))
            ->assertRedirect();
        $this->assertSame(Reservation::ONAY_ONAYLANDI, $reservation->fresh()->onay_durumu);

        // 3) Personel alış yapar (KM sapmasız)
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 09:05'));
        $this->actingAs($employee);
        $this->post(route('app.fleet.reservations.pickup.store', $reservation), [
            'okunan_km' => 10_000,
        ])->assertRedirect();
        $reservation->refresh();
        $vehicle->refresh();
        $this->assertNotNull($reservation->alis_tarihi);
        $this->assertSame(10_000, $reservation->alis_km);
        $this->assertNull($reservation->alis_km_sapma);
        $this->assertSame(Vehicle::DURUM_AKTIF, $vehicle->durum);

        // 4) Personel teslim başvurusu (fotoğraflarla)
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 17:55'));
        $this->post(route('app.fleet.reservations.delivery.store', $reservation), [
            'teslim_km' => 10_150,
            'teslim_beyani' => 1,
            'teslim_foto_on' => UploadedFile::fake()->image('on.jpg'),
            'teslim_foto_arka' => UploadedFile::fake()->image('arka.jpg'),
            'teslim_foto_sag' => UploadedFile::fake()->image('sag.jpg'),
            'teslim_foto_sol' => UploadedFile::fake()->image('sol.jpg'),
            'teslim_foto_km' => UploadedFile::fake()->image('km.jpg'),
        ])->assertRedirect();
        $reservation->refresh();
        $this->assertNotNull($reservation->teslim_basvurusu_tarihi);
        $this->assertNull($reservation->teslim_tarihi);
        $this->assertCount(5, $reservation->teslim_fotograflari);

        // 5) FY teslim onayı verir → araç Garajda, teslim_tarihi dolar
        $this->actingAs($fleetManager);
        $this->post(route('app.fleet.deliveries.confirm', $reservation))
            ->assertRedirect();
        $reservation->refresh();
        $vehicle->refresh();
        $this->assertNotNull($reservation->teslim_tarihi);
        $this->assertSame(Reservation::CLOSURE_NORMAL, $reservation->closure_reason);
        $this->assertSame(Vehicle::DURUM_GARAJDA, $vehicle->durum);
        $this->assertSame(10_150, $vehicle->guncel_km);
    }

    public function test_force_garage_closes_reservation_without_teslim_km(): void
    {
        $employee = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $employee->assignRole('Employee');
        $fm = User::factory()->for($this->tenant)->create();
        $fm->assignRole('Fleet Manager');

        $vehicle = Vehicle::factory()->create(['tenant_id' => $this->tenant->id, 'durum' => Vehicle::DURUM_AKTIF, 'guncel_km' => 5000]);
        $reservation = Reservation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'arac_id' => $vehicle->id,
            'aktif_sofor_id' => $employee->id,
            'onay_durumu' => Reservation::ONAY_ONAYLANDI,
            'alis_tarihi' => now()->subDay(),
            'alis_km' => 5000,
        ]);

        $this->actingAs($fm);
        $this->post(route('app.fleet.deliveries.force-garage', $reservation))->assertRedirect();

        $reservation->refresh();
        $vehicle->refresh();
        $this->assertSame(Reservation::CLOSURE_FILO_FORCED, $reservation->closure_reason);
        $this->assertSame(Vehicle::DURUM_GARAJDA, $vehicle->durum);
        $this->assertSame(5000, $vehicle->guncel_km); // KM güncellenmez
    }
}
