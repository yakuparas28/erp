<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Models\CheckAndNote;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\CheckAndNoteService;
use Modules\Inventory\Models\Partner;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class CheckAndNotePortfolioTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(AccountingDefaultsService::class)->provision($this->tenant);
        $this->actingAs($this->tenantAdmin);
    }

    public function test_registering_an_incoming_check_debits_101_and_credits_120(): void
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);

        app(CheckAndNoteService::class)->register([
            'instrument_no' => 'CHK-001',
            'instrument_type' => 'check',
            'direction' => 'incoming',
            'partner_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'maturity_date' => now()->addMonth()->toDateString(),
            'amount' => '1500.00',
        ], $this->tenantAdmin);

        $entry = JournalEntry::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $lines = JournalEntryLine::withoutGlobalScopes()->where('journal_entry_id', $entry->id)->get();

        $this->assertSame('1500.0000', (string) $lines->where('debit', '!=', '0.0000')->first()->debit);
        $this->assertSame('1500.0000', (string) $lines->where('credit', '!=', '0.0000')->first()->credit);
    }

    public function test_sending_an_incoming_check_to_bank_transitions_to_sent_to_bank(): void
    {
        $note = $this->buildIncoming('CHK-002', '2000');
        $bank = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'bank')->firstOrFail();

        $result = app(CheckAndNoteService::class)->sendToBank($note, $bank->id);

        $this->assertSame(CheckAndNote::STATUS_SENT_TO_BANK, $result->status);
        $this->assertSame($bank->id, $result->collection_bank_journal_id);
    }

    public function test_collecting_an_incoming_check_transitions_to_collected(): void
    {
        $note = $this->buildIncoming('CHK-003', '750');
        $cash = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'cash')->firstOrFail();

        $result = app(CheckAndNoteService::class)->markCollected($note, $cash->id);

        $this->assertSame(CheckAndNote::STATUS_COLLECTED, $result->status);
    }

    public function test_endorsing_an_incoming_check_transitions_to_endorsed(): void
    {
        $note = $this->buildIncoming('CHK-004', '500');
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);

        $result = app(CheckAndNoteService::class)->endorse($note, $supplier->id);

        $this->assertSame(CheckAndNote::STATUS_ENDORSED, $result->status);
        $this->assertSame($supplier->id, $result->endorsed_to_partner_id);
    }

    public function test_bouncing_an_incoming_check_transitions_to_bounced(): void
    {
        $note = $this->buildIncoming('CHK-005', '300');

        $result = app(CheckAndNoteService::class)->markBounced($note);

        $this->assertSame(CheckAndNote::STATUS_BOUNCED, $result->status);
    }

    public function test_registering_an_outgoing_check_debits_320_and_credits_103(): void
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);

        $note = app(CheckAndNoteService::class)->register([
            'instrument_no' => 'OUT-001',
            'instrument_type' => 'check',
            'direction' => 'outgoing',
            'partner_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'maturity_date' => now()->addMonth()->toDateString(),
            'amount' => '1000.00',
        ], $this->tenantAdmin);

        $this->assertSame(CheckAndNote::STATUS_PORTFOLIO, $note->status);
    }

    public function test_marking_outgoing_check_paid_transitions_to_paid(): void
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $note = app(CheckAndNoteService::class)->register([
            'instrument_no' => 'OUT-002',
            'instrument_type' => 'check',
            'direction' => 'outgoing',
            'partner_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'maturity_date' => now()->addMonth()->toDateString(),
            'amount' => '400',
        ], $this->tenantAdmin);
        $bank = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'bank')->firstOrFail();

        $result = app(CheckAndNoteService::class)->markPaid($note, $bank->id);
        $this->assertSame(CheckAndNote::STATUS_PAID, $result->status);
    }

    public function test_outgoing_check_cannot_be_endorsed(): void
    {
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $note = app(CheckAndNoteService::class)->register([
            'instrument_no' => 'OUT-003',
            'instrument_type' => 'check',
            'direction' => 'outgoing',
            'partner_id' => $supplier->id,
            'issue_date' => now()->toDateString(),
            'maturity_date' => now()->addMonth()->toDateString(),
            'amount' => '100',
        ], $this->tenantAdmin);

        $this->expectException(HttpException::class);
        app(CheckAndNoteService::class)->endorse($note, $supplier->id);
    }

    private function buildIncoming(string $no, string $amount): CheckAndNote
    {
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->tenant->id]);

        return app(CheckAndNoteService::class)->register([
            'instrument_no' => $no,
            'instrument_type' => 'check',
            'direction' => 'incoming',
            'partner_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'maturity_date' => now()->addMonth()->toDateString(),
            'amount' => $amount,
        ], $this->tenantAdmin);
    }
}
