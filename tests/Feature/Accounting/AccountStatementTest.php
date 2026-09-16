<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\AccountStatementService;
use Modules\Accounting\Services\JournalEntryService;
use Tests\TenantTestCase;

class AccountStatementTest extends TenantTestCase
{
    private Journal $bank;

    private ChartOfAccount $bankCoa;

    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);

        $this->bankCoa = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', 'like', '102%')
            ->firstOrFail();

        $this->bank = Journal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Bank',
            'type' => 'bank',
            'chart_of_account_id' => $this->bankCoa->id,
            'opening_balance' => '1000',
            'is_active' => true,
        ]);
    }

    public function test_statement_includes_opening_and_computes_running_balance(): void
    {
        $customer120 = $this->defaultsAccount('120');

        // Tenant customer için 500 tahsilat: dr bank(102), cr 120
        app(JournalEntryService::class)->write(
            tenantId: $this->tenant->id,
            journalType: 'bank',
            entryDate: '2026-09-05',
            reference: $this->bank,
            lines: [
                ['account_id' => $this->bankCoa->id, 'debit' => '500', 'credit' => '0'],
                ['account_id' => $customer120->id, 'debit' => '0', 'credit' => '500'],
            ],
        );
        // 200 ödeme (banka çıkışı): dr 320, cr bank(102)
        app(JournalEntryService::class)->write(
            tenantId: $this->tenant->id,
            journalType: 'bank',
            entryDate: '2026-09-10',
            reference: $this->bank,
            lines: [
                ['account_id' => $this->defaultsAccount('320')->id, 'debit' => '200', 'credit' => '0'],
                ['account_id' => $this->bankCoa->id, 'debit' => '0', 'credit' => '200'],
            ],
        );

        $stmt = app(AccountStatementService::class)->build($this->bank, '2026-09-01', '2026-09-30');

        $this->assertSame('1000.0000', $stmt['opening']);
        $this->assertSame('500.0000', $stmt['total_debit']);
        $this->assertSame('200.0000', $stmt['total_credit']);
        $this->assertSame('1300.0000', $stmt['closing']);
        $this->assertCount(2, $stmt['rows']);
        // Running balance: 1000 + 500 = 1500, then 1500 - 200 = 1300
        $this->assertSame('1500.0000', $stmt['rows'][0]['running']);
        $this->assertSame('1300.0000', $stmt['rows'][1]['running']);
    }

    public function test_statement_before_range_folds_into_opening(): void
    {
        $customer = $this->defaultsAccount('120');

        // Aralık dışı (öncesi): 300 tahsilat → opening'e katılmalı
        app(JournalEntryService::class)->write(
            tenantId: $this->tenant->id,
            journalType: 'bank',
            entryDate: '2026-08-15',
            reference: $this->bank,
            lines: [
                ['account_id' => $this->bankCoa->id, 'debit' => '300', 'credit' => '0'],
                ['account_id' => $customer->id, 'debit' => '0', 'credit' => '300'],
            ],
        );

        $stmt = app(AccountStatementService::class)->build($this->bank, '2026-09-01', '2026-09-30');

        // Opening = 1000 (initial) + 300 (Ağustos tahsilatı) = 1300
        $this->assertSame('1300.0000', $stmt['opening']);
        $this->assertCount(0, $stmt['rows']);
        $this->assertSame('1300.0000', $stmt['closing']);
    }

    public function test_journal_without_coa_returns_only_opening(): void
    {
        $bareJournal = Journal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Legacy Cash',
            'type' => 'cash',
            'opening_balance' => '250',
            'is_active' => true,
        ]);

        $stmt = app(AccountStatementService::class)->build($bareJournal);

        $this->assertSame('250.0000', $stmt['opening']);
        $this->assertSame('250.0000', $stmt['closing']);
        $this->assertSame([], $stmt['rows']);
    }

    private function defaultsAccount(string $code): ChartOfAccount
    {
        return app(AccountingDefaultsService::class)->accountByCode($this->tenant->id, $code);
    }
}
