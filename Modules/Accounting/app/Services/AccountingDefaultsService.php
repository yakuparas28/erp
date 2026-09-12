<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Support\TekduzenHesapPlani;

/**
 * Yeni tenant için Tekdüzen Hesap Planı (PRD 3.14) + yevmiye defterleri +
 * 2026 KDV oranları (PRD tax_rates seed'i). Idempotent (firstOrCreate),
 * tenant_id explicit taşınır.
 */
class AccountingDefaultsService
{
    private const JOURNALS = [
        ['name' => 'Satış', 'type' => 'sale'],
        ['name' => 'Alış', 'type' => 'purchase'],
        ['name' => 'Kasa', 'type' => 'cash'],
        ['name' => 'Banka', 'type' => 'bank'],
        ['name' => 'Stok', 'type' => 'stock'],
        ['name' => 'Genel', 'type' => 'general'],
    ];

    private const CURRENCIES = [
        ['code' => 'TRY', 'name' => 'Türk Lirası', 'symbol' => '₺', 'position' => 'after', 'is_functional' => true],
        ['code' => 'USD', 'name' => 'ABD Doları', 'symbol' => '$', 'position' => 'before', 'is_functional' => false],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'position' => 'before', 'is_functional' => false],
    ];

    public function provision(Tenant $tenant): void
    {
        foreach (TekduzenHesapPlani::accounts() as $account) {
            ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type'], 'is_system' => true],
            );
        }

        foreach (self::JOURNALS as $journal) {
            Journal::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'type' => $journal['type']],
                ['name' => $journal['name']],
            );
        }

        $purchaseTaxAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('code', '191')->firstOrFail();
        $saleTaxAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('code', '391')->firstOrFail();

        foreach (['1', '8', '20'] as $rate) {
            TaxRate::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => "KDV %{$rate} (Satış)"],
                ['percentage' => $rate, 'type' => 'sale', 'tax_account_id' => $saleTaxAccount->id],
            );
            TaxRate::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => "KDV %{$rate} (Alış)"],
                ['percentage' => $rate, 'type' => 'purchase', 'tax_account_id' => $purchaseTaxAccount->id],
            );
        }

        foreach (self::CURRENCIES as $currency) {
            Currency::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'position' => $currency['position'],
                    'is_functional' => $currency['is_functional'],
                ],
            );
        }
    }

    public function accountByCode(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('code', $code)->firstOrFail();
    }
}
