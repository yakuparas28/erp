<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Models\CheckAndNote;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\CashFlowService;
use Modules\Accounting\Services\CheckAndNoteService;
use Modules\Inventory\Models\Partner;
use Tests\TenantTestCase;

class CashFlowServiceTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);
    }

    public function test_upcoming_incoming_checks_are_bucketed_by_aging(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);
        $svc = app(CheckAndNoteService::class);

        // 5 gün sonra vadeli — 0_7 bucket
        $svc->register([
            'instrument_no' => 'A', 'instrument_type' => 'check', 'direction' => 'incoming',
            'partner_id' => $customer->id, 'issue_date' => now()->toDateString(),
            'maturity_date' => now()->addDays(5)->toDateString(), 'amount' => '100',
        ], $this->tenantAdmin);

        // 20 gün sonra — 8_30 bucket
        $svc->register([
            'instrument_no' => 'B', 'instrument_type' => 'check', 'direction' => 'incoming',
            'partner_id' => $customer->id, 'issue_date' => now()->toDateString(),
            'maturity_date' => now()->addDays(20)->toDateString(), 'amount' => '200',
        ], $this->tenantAdmin);

        // 3 gün önce vadeli — overdue
        $note = new CheckAndNote([
            'instrument_no' => 'C', 'instrument_type' => 'check', 'direction' => 'incoming',
            'partner_id' => $customer->id, 'issue_date' => now()->subDays(30)->toDateString(),
            'maturity_date' => now()->subDays(3)->toDateString(), 'amount' => '50',
            'status' => CheckAndNote::STATUS_PORTFOLIO, 'status_changed_at' => now()->toDateString(),
        ]);
        $note->tenant_id = $this->tenant->id;
        $note->save();

        $buckets = app(CashFlowService::class)->upcomingChecksBuckets($this->tenant->id, 'incoming');

        $this->assertSame(1, $buckets['0_7']['count']);
        $this->assertSame('100.0000', $buckets['0_7']['total']);
        $this->assertSame(1, $buckets['8_30']['count']);
        $this->assertSame('200.0000', $buckets['8_30']['total']);
        $this->assertSame(1, $buckets['overdue']['count']);
        $this->assertSame('50.0000', $buckets['overdue']['total']);
    }

    public function test_projection_returns_three_horizons(): void
    {
        $rows = app(CashFlowService::class)->projection($this->tenant->id);
        $this->assertCount(3, $rows);
        $this->assertSame(30, $rows[0]['days']);
        $this->assertSame(60, $rows[1]['days']);
        $this->assertSame(90, $rows[2]['days']);
    }

    public function test_account_balances_returns_row_per_active_journal(): void
    {
        $rows = app(CashFlowService::class)->accountBalances($this->tenant->id);
        $this->assertGreaterThan(0, count($rows));
        foreach ($rows as $row) {
            $this->assertIsString($row['balance']);
            $this->assertContains($row['journal']->type, ['cash', 'bank']);
        }
    }
}
