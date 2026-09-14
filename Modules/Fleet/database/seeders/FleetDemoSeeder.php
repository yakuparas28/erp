<?php

namespace Modules\Fleet\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Fleet\Models\FleetTaskSetting;
use Modules\Fleet\Models\MaintenanceRecord;
use Modules\Fleet\Models\Project;
use Modules\Fleet\Models\Reservation;
use Modules\Fleet\Models\Vehicle;
use Modules\Fleet\Models\VehicleCalendarBlock;
use Modules\Fleet\Models\VehicleUsageRule;
use Modules\Fleet\Services\FleetDefaultsService;

/**
 * Tek tenant için (varsayılan: "Test Firma") Fleet modülünü hızlı görsel test
 * verisiyle doldurur: 1 Fleet Manager kullanıcısı, 4 proje, 8 araç (farklı
 * durum + MTV kombinasyonlarıyla), 4 örnek rezervasyon (pending/approved/
 * teslim bekleyen/tamamlanan), 3 takvim bloğu, 2 bakım kaydı, kullanım
 * kuralları içeriği ve zamanlanmış görev ayarları. Sürücü hesabı olarak
 * HrDemoSeeder'ın oluşturduğu personel kullanıcılarını yeniden kullanır;
 * bulunamazsa Fleet için minimum kullanıcı üretir. Tamamen idempotent.
 */
class FleetDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantName = env('FLEET_DEMO_TENANT', 'Test Firma');
        $tenant = Tenant::where('name', $tenantName)->firstOrFail();

        setPermissionsTeamId($tenant->id);
        app(FleetDefaultsService::class)->provision($tenant);

        $fleetManager = $this->seedFleetManager($tenant);
        $drivers = $this->collectDrivers($tenant);
        $projects = $this->seedProjects($tenant);
        $vehicles = $this->seedVehicles($tenant);
        $this->seedCalendarBlocks($tenant, $vehicles, $fleetManager);
        $this->seedMaintenanceRecords($tenant, $vehicles, $fleetManager);
        $this->seedReservations($tenant, $vehicles, $drivers, $projects);
        $this->seedUsageRule($tenant, $fleetManager);
        $this->seedTaskSetting($tenant);

        $this->command?->info("Fleet demo data seeded for tenant: {$tenant->name}");
    }

    private function seedFleetManager(Tenant $tenant): User
    {
        $user = User::firstOrCreate(
            ['email' => 'kemal.aslan@testfirma.local'],
            ['name' => 'Kemal Aslan', 'password' => 'password', 'tenant_id' => $tenant->id],
        );
        setPermissionsTeamId($tenant->id);
        $user->syncRoles(['Fleet Manager']);

        return $user;
    }

    /** @return list<User> */
    private function collectDrivers(Tenant $tenant): array
    {
        // HrDemoSeeder'dan gelen 5 personel + Tenant Admin (Ali Yıldız) sürücü olabilir.
        return User::where('tenant_id', $tenant->id)
            ->whereIn('email', [
                'ali.yildiz@testfirma.local',
                'ayşe.kaya@testfirma.local', 'ayse.kaya@testfirma.local',
                'mehmet.demir@testfirma.local',
                'zeynep.şahin@testfirma.local', 'zeynep.sahin@testfirma.local',
                'emre.öz@testfirma.local', 'emre.oz@testfirma.local',
                'selin.aksoy@testfirma.local',
            ])
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @return array<string, Project> */
    private function seedProjects(Tenant $tenant): array
    {
        $data = [
            'İstanbul Şantiye' => '2025-03-01',
            'Ankara Kampüs Kurulumu' => '2025-06-15',
            'İzmir Bakım Turu' => '2026-01-10',
            'Bursa Müşteri Ziyaretleri' => null,
        ];
        $out = [];
        foreach ($data as $ad => $baslangic) {
            $out[$ad] = Project::updateOrCreate(
                ['tenant_id' => $tenant->id, 'ad' => $ad],
                ['baslangic_tarihi' => $baslangic, 'aktif' => true],
            );
        }

        return $out;
    }

    /** @return list<Vehicle> */
    private function seedVehicles(Tenant $tenant): array
    {
        $today = today();

        $data = [
            // [plaka, marka_model, yil, km, bakim, muayene, mtv, mtv_durum, durum]
            ['34 ABC 001', 'Ford Transit 350L', 2022, 78_500, $today->copy()->addDays(45), $today->copy()->addMonths(4), $today->copy()->addDays(70), Vehicle::MTV_ODENDI, Vehicle::DURUM_GARAJDA],
            ['34 DEF 202', 'Renault Kangoo Multix', 2021, 112_400, $today->copy()->addDays(5), $today->copy()->addMonths(6), $today->copy()->addDays(20), Vehicle::MTV_ODENMEDI, Vehicle::DURUM_GARAJDA],
            ['06 GHK 303', 'Toyota Corolla 1.6', 2023, 42_100, $today->copy()->addDays(90), $today->copy()->addMonths(8), $today->copy()->addDays(3), Vehicle::MTV_KISMI, Vehicle::DURUM_GARAJDA],
            ['06 JKL 404', 'Fiat Doblo 1.3', 2020, 155_800, $today->copy()->subDays(20), $today->copy()->addMonths(2), $today->copy()->subDays(10), Vehicle::MTV_ODENMEDI, Vehicle::DURUM_BLOKELI],
            ['35 MNO 505', 'Volkswagen Caddy', 2024, 18_300, $today->copy()->addDays(180), $today->copy()->addMonths(12), $today->copy()->addMonths(6), Vehicle::MTV_ODENDI, Vehicle::DURUM_AKTIF],
            ['16 PRS 606', 'Peugeot Partner', 2019, 198_700, $today->copy()->addDays(10), $today->copy()->addMonths(1), $today->copy()->addDays(45), Vehicle::MTV_ODENMEDI, Vehicle::DURUM_GARAJDA],
            ['34 TUV 707', 'Dacia Duster 4x4', 2023, 55_200, $today->copy()->addDays(60), $today->copy()->addMonths(9), $today->copy()->addMonths(4), Vehicle::MTV_ODENDI, Vehicle::DURUM_GARAJDA],
            ['06 XYZ 808', 'Ford Tourneo Custom', 2022, 88_400, $today->copy()->addDays(120), $today->copy()->addMonths(7), $today->copy()->addDays(2), Vehicle::MTV_ODENMEDI, Vehicle::DURUM_GARAJDA],
        ];

        $vehicles = [];
        foreach ($data as [$plaka, $marka, $yil, $km, $bakim, $muayene, $mtv, $mtvDurum, $durum]) {
            $vehicles[] = Vehicle::updateOrCreate(
                ['tenant_id' => $tenant->id, 'plaka' => $plaka],
                [
                    'marka_model' => $marka,
                    'yil' => $yil,
                    'guncel_km' => $km,
                    'bakim_tarihi' => $bakim,
                    'muayene_tarihi' => $muayene,
                    'mtv_odeme_tarihi' => $mtv,
                    'mtv_odeme_durumu' => $mtvDurum,
                    'durum' => $durum,
                    'sasi_no' => 'VF1'.strtoupper(substr(md5($plaka), 0, 14)),
                    'motor_no' => 'MTR-'.strtoupper(substr(md5($plaka.'m'), 0, 10)),
                ],
            );
        }

        return $vehicles;
    }

    /** @param list<Vehicle> $vehicles */
    private function seedCalendarBlocks(Tenant $tenant, array $vehicles, User $fm): void
    {
        if (VehicleCalendarBlock::where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $today = today();
        $blocks = [
            // Blokeli araç için aktif bakım bloğu
            [$vehicles[3], VehicleCalendarBlock::TYPE_BAKIM, $today->copy()->subDays(5), $today->copy()->addDays(3), 'Turbo değişimi + motor revizyonu'],
            // Yaklaşan muayene bloğu
            [$vehicles[5], VehicleCalendarBlock::TYPE_MUAYENE, $today->copy()->addDays(28), $today->copy()->addDays(28), 'Muayene randevusu'],
            // Yaklaşan bakım
            [$vehicles[1], VehicleCalendarBlock::TYPE_BAKIM, $today->copy()->addDays(4), $today->copy()->addDays(5), 'Periyodik bakım — 120.000 km'],
        ];

        foreach ($blocks as [$v, $type, $start, $end, $note]) {
            VehicleCalendarBlock::create([
                'tenant_id' => $tenant->id,
                'vehicle_id' => $v->id,
                'block_type' => $type,
                'start_date' => $start,
                'end_date' => $end,
                'aciklama' => $note,
                'created_by_id' => $fm->id,
            ]);
        }
    }

    /** @param list<Vehicle> $vehicles */
    private function seedMaintenanceRecords(Tenant $tenant, array $vehicles, User $fm): void
    {
        if (MaintenanceRecord::where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        MaintenanceRecord::create([
            'tenant_id' => $tenant->id,
            'arac_id' => $vehicles[0]->id,
            'giris_yapan_id' => $fm->id,
            'islem_turu' => MaintenanceRecord::ISLEM_BAKIM,
            'yapilan_islemler' => 'Yağ + filtre değişimi, fren balata kontrolü, lastik rotasyon',
            'degisen_parcalar' => 'Motor yağı 5W-30, yağ filtresi, hava filtresi',
            'yeni_bakim_tarihi' => today()->copy()->addMonths(6),
            'kayit_tarihi' => today()->copy()->subDays(30),
        ]);

        MaintenanceRecord::create([
            'tenant_id' => $tenant->id,
            'arac_id' => $vehicles[4]->id,
            'giris_yapan_id' => $fm->id,
            'islem_turu' => MaintenanceRecord::ISLEM_MUAYENE,
            'yapilan_islemler' => 'TÜVTürk muayene — geçti',
            'yeni_muayene_tarihi' => today()->copy()->addYear(),
            'kayit_tarihi' => today()->copy()->subDays(10),
        ]);
    }

    /**
     * @param  list<Vehicle>  $vehicles
     * @param  list<User>  $drivers
     * @param  array<string, Project>  $projects
     */
    private function seedReservations(Tenant $tenant, array $vehicles, array $drivers, array $projects): void
    {
        if (Reservation::where('tenant_id', $tenant->id)->exists()) {
            return;
        }
        if (count($drivers) < 2) {
            return; // demo çalıştırılmadıysa sessiz geç
        }

        $now = now();
        $projectList = array_values($projects);

        // 1) Pending: yarınki yolculuk için onay bekleyen
        Reservation::create([
            'tenant_id' => $tenant->id,
            'arac_id' => $vehicles[0]->id,
            'aktif_sofor_id' => $drivers[1]->id,
            'proje_id' => $projectList[0]->id,
            'planlanan_alis_at' => $now->copy()->addDay()->setTime(9, 0),
            'planlanan_teslim_at' => $now->copy()->addDay()->setTime(18, 0),
            'talep_tarihi' => $now->copy()->subHours(3),
            'onay_durumu' => Reservation::ONAY_BEKLEMEDE,
            'teslim_beyani' => false,
        ]);

        // 2) Approved + alış yapılmış, teslim bekleyen (aktif yolculuk)
        Reservation::create([
            'tenant_id' => $tenant->id,
            'arac_id' => $vehicles[4]->id, // Volkswagen Caddy — zaten Aktif_Kullanimda
            'aktif_sofor_id' => $drivers[2]->id,
            'proje_id' => $projectList[1]->id,
            'planlanan_alis_at' => $now->copy()->subDay(),
            'planlanan_teslim_at' => $now->copy()->addDays(2),
            'talep_tarihi' => $now->copy()->subDays(3),
            'alis_tarihi' => $now->copy()->subDay(),
            'alis_km' => 18_200,
            'alis_km_sapma' => -100,
            'onay_durumu' => Reservation::ONAY_ONAYLANDI,
            'teslim_beyani' => false,
        ]);

        // 3) Teslim başvurusu yapılmış (Filo Yöneticisi onayı bekleyen)
        Reservation::create([
            'tenant_id' => $tenant->id,
            'arac_id' => $vehicles[6]->id,
            'aktif_sofor_id' => $drivers[3]->id,
            'proje_id' => $projectList[2]->id,
            'planlanan_alis_at' => $now->copy()->subDays(3),
            'planlanan_teslim_at' => $now->copy()->subHours(2),
            'talep_tarihi' => $now->copy()->subDays(4),
            'alis_tarihi' => $now->copy()->subDays(3),
            'alis_km' => 55_200,
            'teslim_km' => 55_680,
            'teslim_basvurusu_tarihi' => $now->copy()->subHours(2),
            'teslim_beyani' => true,
            'onay_durumu' => Reservation::ONAY_ONAYLANDI,
        ]);

        // 4) Tamamlanmış (rapora düşer)
        Reservation::create([
            'tenant_id' => $tenant->id,
            'arac_id' => $vehicles[0]->id,
            'aktif_sofor_id' => $drivers[4] ?? $drivers[1],
            'proje_id' => $projectList[3]->id,
            'planlanan_alis_at' => $now->copy()->subDays(12),
            'planlanan_teslim_at' => $now->copy()->subDays(10),
            'talep_tarihi' => $now->copy()->subDays(13),
            'alis_tarihi' => $now->copy()->subDays(12),
            'alis_km' => 78_000,
            'teslim_km' => 78_500,
            'teslim_basvurusu_tarihi' => $now->copy()->subDays(10),
            'teslim_tarihi' => $now->copy()->subDays(10)->addHours(2),
            'teslim_beyani' => true,
            'onay_durumu' => Reservation::ONAY_ONAYLANDI,
            'closure_reason' => Reservation::CLOSURE_NORMAL,
        ]);

        // 5) Reddedilmiş
        Reservation::create([
            'tenant_id' => $tenant->id,
            'arac_id' => $vehicles[2]->id,
            'aktif_sofor_id' => $drivers[1]->id,
            'proje_id' => null,
            'planlanan_alis_at' => $now->copy()->addDays(5)->setTime(8, 0),
            'planlanan_teslim_at' => $now->copy()->addDays(5)->setTime(20, 0),
            'talep_tarihi' => $now->copy()->subHour(),
            'onay_durumu' => Reservation::ONAY_REDDEDILDI,
            'red_aciklamasi' => 'O gün bakım randevusu var, başka bir araca yönlendirin.',
            'teslim_beyani' => false,
        ]);
    }

    private function seedUsageRule(Tenant $tenant, User $fm): void
    {
        $rule = VehicleUsageRule::forTenant($tenant->id);
        if (trim((string) $rule->icerik_html) !== '') {
            return;
        }
        $rule->update([
            'icerik_html' => <<<'HTML'
<h3>Araç Kullanım Kuralları</h3>
<ul>
    <li>Aracı teslim aldığınızda kilometre değerini sistemdeki değerle karşılaştırın; sapma varsa mutlaka not düşün.</li>
    <li>Sigara içmek yasaktır.</li>
    <li>Şehirlerarası yolculuklarda 4 saatte bir mola verin.</li>
    <li>Kaza / arıza durumunda önce Filo Yöneticisini arayın: <strong>0(555) 000-0000</strong>.</li>
    <li>Yakıt fişlerini muhasebeye teslim edilmek üzere saklayın.</li>
    <li>Aracı teslim ederken tüm yönlerden ve kilometreden fotoğraf yükleyin.</li>
</ul>
HTML,
            'updated_by_id' => $fm->id,
        ]);
    }

    private function seedTaskSetting(Tenant $tenant): void
    {
        FleetTaskSetting::forTenant($tenant->id)->update([
            'critical_window_enabled' => true,
            'critical_window_days' => 14,
            'mtv_reminder_days' => 30,
        ]);
    }
}
