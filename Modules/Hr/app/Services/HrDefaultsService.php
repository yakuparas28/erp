<?php

namespace Modules\Hr\Services;

use App\Models\Tenant;
use Modules\Hr\Models\ConsumptionRule;
use Modules\Hr\Models\LeaveHourConfig;
use Modules\Hr\Models\LeaveType;

/**
 * Yeni bir tenant provizyonu tamamlanınca HR modülünün baz kataloğunu
 * (izin türleri, tüketim/hakediş kuralları, mazeret izni yapılandırması)
 * seed eder. TenantProvisioningService bunu InventoryDefaultsService ve
 * AccountingDefaultsService'in yanında zincirli çağırır — modül aktif
 * olmasa bile kayıtlar hazırdır, aktive edilince ekranlar dolu gelir.
 */
class HrDefaultsService
{
    public function provision(Tenant $tenant): void
    {
        $this->seedLeaveTypes($tenant);
        $this->seedConsumptionRules($tenant);
        $this->seedHourlyLeaveConfig($tenant);
    }

    private function seedLeaveTypes(Tenant $tenant): void
    {
        foreach ($this->leaveTypeDefaults() as $data) {
            LeaveType::updateOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $data['key']],
                array_merge($data, ['tenant_id' => $tenant->id]),
            );
        }
    }

    private function seedConsumptionRules(Tenant $tenant): void
    {
        foreach ($this->consumptionRuleDefaults() as $data) {
            ConsumptionRule::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $data['code']],
                array_merge($data, ['tenant_id' => $tenant->id]),
            );
        }
    }

    private function seedHourlyLeaveConfig(Tenant $tenant): void
    {
        LeaveHourConfig::firstOrCreate(
            ['tenant_id' => $tenant->id, 'department_id' => null],
            [
                'daily_work_hours' => 8,
                'monthly_leave_hours' => 24,
                'min_hours' => 1,
                'negative_balance_policy' => 'strict',
                'is_active' => true,
            ],
        );
    }

    /**
     * Demo VatPortal İK modülündeki varsayılan 9 tür (4857 Sayılı İş Kanunu esaslı).
     *
     * @return list<array<string, mixed>>
     */
    private function leaveTypeDefaults(): array
    {
        return [
            ['key' => 'yillik', 'name' => 'Yıllık İzin', 'unit' => 'day', 'deducts_from_balance' => true, 'requires_document' => false, 'requires_second_level' => true, 'max_days_per_year' => null, 'is_active' => true],
            ['key' => 'mazeret', 'name' => 'Mazeret İzni', 'unit' => 'hour', 'deducts_from_balance' => false, 'requires_document' => false, 'requires_second_level' => false, 'max_days_per_year' => 10, 'is_active' => true],
            ['key' => 'hastalik', 'name' => 'Hastalık İzni', 'unit' => 'day', 'deducts_from_balance' => false, 'requires_document' => false, 'requires_second_level' => true, 'max_days_per_year' => 10, 'is_active' => true],
            ['key' => 'ucretsiz', 'name' => 'Ücretsiz İzin', 'unit' => 'day', 'deducts_from_balance' => false, 'requires_document' => false, 'requires_second_level' => true, 'max_days_per_year' => null, 'is_active' => true],
            ['key' => 'dogum', 'name' => 'Doğum İzni', 'unit' => 'day', 'deducts_from_balance' => false, 'requires_document' => true, 'requires_second_level' => true, 'max_days_per_year' => 10, 'is_active' => true],
            ['key' => 'olum', 'name' => 'Ölüm İzni', 'unit' => 'day', 'deducts_from_balance' => false, 'requires_document' => true, 'requires_second_level' => true, 'max_days_per_year' => 10, 'is_active' => true],
            ['key' => 'evlenme', 'name' => 'Evlenme İzni', 'unit' => 'day', 'deducts_from_balance' => false, 'requires_document' => true, 'requires_second_level' => true, 'max_days_per_year' => 10, 'is_active' => true],
            ['key' => 'kismi_ucretli', 'name' => 'Kısmi Ücretli İzin', 'unit' => 'day', 'deducts_from_balance' => false, 'requires_document' => false, 'requires_second_level' => true, 'max_days_per_year' => null, 'is_active' => false],
            ['key' => 'tasinma', 'name' => 'Taşınma İzni', 'unit' => 'day', 'deducts_from_balance' => false, 'requires_document' => true, 'requires_second_level' => true, 'max_days_per_year' => 10, 'is_active' => false],
        ];
    }

    /**
     * 8 varsayılan tüketim/hakediş kuralı (4857 Sayılı İş Kanunu esaslı).
     *
     * @return list<array<string, mixed>>
     */
    private function consumptionRuleDefaults(): array
    {
        return [
            ['code' => 'HK-01', 'category' => 'accrual', 'name' => '1 Tam Yıl Şartı', 'legal_basis' => '4857 Sayılı İş Kanunu, Madde 53', 'description' => 'İşe giriş tarihinden itibaren 1 tam yıl (365 gün) dolmadan yıllık izin bakiyesi 0 hesaplanır.', 'is_active' => true],
            ['code' => 'HK-02', 'category' => 'accrual', 'name' => 'Kıdeme Göre Kademe Artışı', 'legal_basis' => '4857 Sayılı İş Kanunu, Madde 53', 'description' => '1-5 yıl → 14 gün, 5-15 yıl → 20 gün, 15+ yıl → 26 gün. 18 yaş altı ve 50 yaş üstü en az 20 gün.', 'is_active' => true],
            ['code' => 'HK-03', 'category' => 'accrual', 'name' => 'Hakediş Öteleme', 'legal_basis' => '4857 Sayılı İş Kanunu, Madde 55', 'description' => 'Ücretsiz izin, mazeretsiz devamsızlık ve işçi kusurlu raporlar hakediş tarihini kullanılan gün kadar öteler.', 'is_active' => true],
            ['code' => 'TK-01', 'category' => 'consumption', 'name' => 'Cumartesi İş Günü Sayılır', 'legal_basis' => '4857 Sayılı İş Kanunu, Madde 46/56', 'description' => 'Haftalık 45 saat 5 güne dağıtılmışsa Cumartesi yasal iş günü sayılır ve bakiyeden düşülür.', 'is_active' => true],
            ['code' => 'TK-02', 'category' => 'consumption', 'name' => 'Cuma + Cumartesi + 1 Gün', 'legal_basis' => '4857 Sayılı İş Kanunu, Madde 46/56', 'description' => 'Cuma gününü içeren her izin, Cumartesi de dahil edilerek +1 gün hesaplanır.', 'is_active' => false],
            ['code' => 'TK-03', 'category' => 'consumption', 'name' => 'İzin Bölünmesine Müsaade', 'legal_basis' => '4857 Sayılı İş Kanunu, Madde 56', 'description' => 'Aktif: yıllık izin parçalı kullanılabilir. Pasif: yıllık iznin ilk bölümü 10 iş günü bütün olarak kullandırılır.', 'is_active' => true],
            ['code' => 'TK-04', 'category' => 'consumption', 'name' => 'Rapor-İzin Çakışması', 'legal_basis' => 'Yargıtay İçtihatları', 'description' => 'Yıllık izindeyken alınan istirahat raporu izni durdurur; raporlu günler bakiyeden düşülmez ve bakiyeye iade edilir.', 'is_active' => true],
            ['code' => 'TK-05', 'category' => 'consumption', 'name' => 'Yol İzni', 'legal_basis' => '4857 Sayılı İş Kanunu, Madde 56', 'description' => 'Başka ilde geçirilecek izinlerde belgelenmesi şartıyla 4 güne kadar ücretsiz yol izni. Bilet/PNR girişi zorunlu.', 'is_active' => true],
        ];
    }
}
