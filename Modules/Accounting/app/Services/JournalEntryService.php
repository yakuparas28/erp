<?php

namespace Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;

/**
 * Otomatik yevmiye kayıt motoru (PRD 3.12). Hiçbir kayıt elle girilmez;
 * bu servis yalnızca sistem tarafından (event listener'lar veya
 * InvoiceService/PaymentService) çağrılır. write() denge doğrulamasını
 * (SUM(debit)=SUM(credit)) posted'a geçmeden ÖNCE yapar — DB::raw/trigger
 * yasak olduğundan bu kural uygulama katmanında zorlanır (PRD YENİ KURAL).
 */
class JournalEntryService
{
    public function __construct(private readonly AccountingDefaultsService $defaults) {}

    /**
     * @param  list<array{account_id: int, debit: string, credit: string}>  $lines
     */
    public function write(
        int $tenantId,
        string $journalType,
        string $entryDate,
        Model $reference,
        array $lines,
    ): JournalEntry {
        $totalDebit = array_reduce($lines, fn (string $carry, array $line) => bcadd($carry, $line['debit'], 4), '0.0000');
        $totalCredit = array_reduce($lines, fn (string $carry, array $line) => bcadd($carry, $line['credit'], 4), '0.0000');

        abort_if(bccomp($totalDebit, $totalCredit, 4) !== 0, 422, __('Journal entry debits and credits must balance.'));

        $journal = Journal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('type', $journalType)->firstOrFail();

        return DB::transaction(function () use ($tenantId, $journal, $entryDate, $reference, $lines): JournalEntry {
            $entry = new JournalEntry(['journal_id' => $journal->id, 'entry_date' => $entryDate, 'status' => 'posted']);
            $entry->tenant_id = $tenantId;
            $entry->reference()->associate($reference);
            $entry->save();

            foreach ($lines as $line) {
                $entryLine = new JournalEntryLine([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
                $entryLine->tenant_id = $tenantId;
                $entryLine->save();
            }

            return $entry;
        });
    }
}
