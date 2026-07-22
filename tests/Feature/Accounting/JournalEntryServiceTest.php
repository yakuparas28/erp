<?php

namespace Tests\Feature\Accounting;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Inventory\Models\Product;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class JournalEntryServiceTest extends TenantTestCase
{
    public function test_write_creates_a_balanced_posted_journal_entry_with_lines(): void
    {
        $journal = Journal::factory()->for($this->tenant)->create(['type' => 'general']);
        $cashAccount = ChartOfAccount::factory()->for($this->tenant)->create(['code' => '100']);
        $salesAccount = ChartOfAccount::factory()->for($this->tenant)->create(['code' => '600']);
        $product = Product::factory()->for($this->tenant)->create();

        $entry = app(JournalEntryService::class)->write(
            tenantId: $this->tenant->id,
            journalType: 'general',
            entryDate: '2026-07-22',
            reference: $product,
            lines: [
                ['account_id' => $cashAccount->id, 'debit' => '100.0000', 'credit' => '0.0000'],
                ['account_id' => $salesAccount->id, 'debit' => '0.0000', 'credit' => '100.0000'],
            ],
        );

        $this->assertSame('posted', $entry->status);
        $this->assertSame($journal->id, $entry->journal_id);
        $this->assertSame('product', $entry->reference_type);
        $this->assertSame($product->id, $entry->reference_id);

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_entry_lines', 2);

        $this->assertSame(2, JournalEntryLine::withoutGlobalScopes()
            ->where('journal_entry_id', $entry->id)->count());
    }

    public function test_write_aborts_with_422_when_debits_and_credits_do_not_balance(): void
    {
        Journal::factory()->for($this->tenant)->create(['type' => 'general']);
        $cashAccount = ChartOfAccount::factory()->for($this->tenant)->create(['code' => '100']);
        $salesAccount = ChartOfAccount::factory()->for($this->tenant)->create(['code' => '600']);
        $product = Product::factory()->for($this->tenant)->create();

        try {
            app(JournalEntryService::class)->write(
                tenantId: $this->tenant->id,
                journalType: 'general',
                entryDate: '2026-07-22',
                reference: $product,
                lines: [
                    ['account_id' => $cashAccount->id, 'debit' => '100.0000', 'credit' => '0.0000'],
                    ['account_id' => $salesAccount->id, 'debit' => '0.0000', 'credit' => '50.0000'],
                ],
            );

            $this->fail('Expected an HttpException to be thrown for unbalanced journal entry lines.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_entry_lines', 0);
    }
}
