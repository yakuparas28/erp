<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\TaxRate;

/**
 * Yeni tenant için Tekdüzen Hesap Planı (PRD 3.14) + yevmiye defterleri +
 * 2026 KDV oranları (PRD tax_rates seed'i). Idempotent (firstOrCreate),
 * tenant_id explicit taşınır.
 */
class AccountingDefaultsService
{
    private const ACCOUNTS = [
        ['code' => '100', 'name' => 'Kasa', 'type' => 'asset'],
        ['code' => '102', 'name' => 'Bankalar', 'type' => 'asset'],
        ['code' => '120', 'name' => 'Alıcılar', 'type' => 'asset'],
        ['code' => '153', 'name' => 'Ticari Mallar', 'type' => 'asset'],
        ['code' => '191', 'name' => 'İndirilecek KDV', 'type' => 'asset'],
        ['code' => '320', 'name' => 'Satıcılar', 'type' => 'liability'],
        ['code' => '391', 'name' => 'Hesaplanan KDV', 'type' => 'liability'],
        ['code' => '600', 'name' => 'Yurtiçi Satışlar', 'type' => 'income'],
        ['code' => '621', 'name' => 'Satılan Ticari Mallar Maliyeti', 'type' => 'expense'],
        ['code' => '646', 'name' => 'Kambiyo Karları', 'type' => 'income'],
        ['code' => '656', 'name' => 'Kambiyo Zararları', 'type' => 'expense'],
    ];

    private const JOURNALS = [
        ['name' => 'Satış', 'type' => 'sale'],
        ['name' => 'Alış', 'type' => 'purchase'],
        ['name' => 'Kasa', 'type' => 'cash'],
        ['name' => 'Banka', 'type' => 'bank'],
        ['name' => 'Stok', 'type' => 'stock'],
        ['name' => 'Genel', 'type' => 'general'],
    ];

    private const CURRENCIES = [
        ['code' => 'TRY', 'name' => 'Türk Lirası', 'is_functional' => true],
        ['code' => 'USD', 'name' => 'ABD Doları', 'is_functional' => false],
        ['code' => 'EUR', 'name' => 'Euro', 'is_functional' => false],
    ];

    public function provision(Tenant $tenant): void
    {
        foreach (self::ACCOUNTS as $account) {
            ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type']],
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
                ['name' => $currency['name'], 'is_functional' => $currency['is_functional']],
            );
        }
    }

    public function accountByCode(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('code', $code)->firstOrFail();
    }
}
