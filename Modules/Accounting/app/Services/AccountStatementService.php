<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;

/**
 * Kasa/Banka hesabı ekstresi. journal_entry_lines'ı chart_of_account_id
 * ile filtreleyip yürüyen bakiye ile birlikte döner. Kaynak (invoice /
 * payment / check_and_note / card_payment / payroll_period / payslip /
 * salary_advance / bank_statement...) morph üzerinden çözülür ve
 * gösterilir.
 */
class AccountStatementService
{
    /**
     * @return array{
     *   opening: string,
     *   closing: string,
     *   total_debit: string,
     *   total_credit: string,
     *   rows: array<int, array{date:string, description:string, ref:?string, debit:string, credit:string, running:string, entry_id:int}>
     * }
     */
    public function build(Journal $journal, ?string $from = null, ?string $to = null): array
    {
        if ($journal->chart_of_account_id === null) {
            return [
                'opening' => (string) ($journal->opening_balance ?? '0'),
                'closing' => (string) ($journal->opening_balance ?? '0'),
                'total_debit' => '0.0000',
                'total_credit' => '0.0000',
                'rows' => [],
            ];
        }

        // Aralıktan önceki bakiye = opening_balance + net(entries < from)
        $priorNet = '0';
        if ($from !== null) {
            $priorSum = JournalEntryLine::withoutGlobalScopes()
                ->where('tenant_id', $journal->tenant_id)
                ->where('account_id', $journal->chart_of_account_id)
                ->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<', $from))
                ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as bal')
                ->value('bal');
            $priorNet = (string) ($priorSum ?? '0');
        }
        $opening = bcadd((string) ($journal->opening_balance ?? '0'), $priorNet, 4);

        $query = JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $journal->tenant_id)
            ->where('account_id', $journal->chart_of_account_id)
            ->with(['journalEntry' => fn ($q) => $q->orderBy('entry_date')->orderBy('id')]);

        if ($from !== null) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '>=', $from));
        }
        if ($to !== null) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $to));
        }

        $lines = $query->get()
            ->filter(fn ($l) => $l->journalEntry !== null)
            ->sortBy(fn ($l) => [$l->journalEntry->entry_date->toDateString(), $l->journalEntry->id])
            ->values();

        $running = $opening;
        $totalDebit = '0.0000';
        $totalCredit = '0.0000';
        $rows = [];

        foreach ($lines as $line) {
            $entry = $line->journalEntry;
            $running = bcadd($running, bcsub((string) $line->debit, (string) $line->credit, 4), 4);
            $totalDebit = bcadd($totalDebit, (string) $line->debit, 4);
            $totalCredit = bcadd($totalCredit, (string) $line->credit, 4);

            $rows[] = [
                'date' => $entry->entry_date->format('d.m.Y'),
                'description' => $this->describe($entry),
                'ref' => $entry->reference_type ? $entry->reference_type.'#'.$entry->reference_id : null,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'running' => $running,
                'entry_id' => $entry->id,
            ];
        }

        return [
            'opening' => $opening,
            'closing' => $running,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'rows' => $rows,
        ];
    }

    private function describe(JournalEntry $entry): string
    {
        $labels = [
            'invoice' => 'Fatura',
            'payment' => 'Tahsilat/Ödeme',
            'check_and_note' => 'Çek/Senet',
            'card_payment' => 'Kart Tahsilatı',
            'payroll_period' => 'Bordro Dönemi',
            'payslip' => 'Bordro',
            'salary_advance' => 'Personel Avansı',
            'stock_move' => 'Stok Hareketi',
            'bank_statement_line' => 'Banka Ekstresi',
            'purchase_order_line' => 'Satın Alma Satırı',
            'sales_order_line' => 'Satış Satırı',
        ];
        $type = $entry->reference_type;
        $label = $labels[$type] ?? ucfirst((string) $type);

        return $label.' #'.$entry->reference_id;
    }
}
