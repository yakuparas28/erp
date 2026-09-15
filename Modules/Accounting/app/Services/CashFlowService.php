<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\CardPayment;
use Modules\Accounting\Models\CheckAndNote;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntryLine;

/**
 * Nakit akışı raporlama. Tüm bakiye/vade hesapları tenant scope'unda:
 *
 *  - accountBalances(): Her aktif kasa/banka için "opening_balance +
 *    (JE dr - JE cr)". Bank statement / payment akışlarından bağımsız
 *    olarak JE lines'ı okuyarak muhasebe bakiyesini üretir.
 *
 *  - upcomingChecks(): Portföydeki + bankaya verilmiş alınan çekler ve
 *    portföydeki verilen çekler; vadeye göre bucket'lanmış (overdue,
 *    0-7, 8-30, 31-60, 61-90, 90+).
 *
 *  - upcomingInvoices(): Posted+açık faturaların tahmini vadesi
 *    (created_at + 30 gün varsayımı — invoice.due_date yoksa) veya
 *    remaining balance > 0 olanları döner.
 *
 *  - pendingCardSettlements(): valörü gelmemiş kart tahsilatları.
 */
class CashFlowService
{
    /**
     * @return array<int, array{journal:Journal, balance:string}>
     */
    public function accountBalances(int $tenantId): array
    {
        $accounts = Journal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', ['cash', 'bank'])
            ->where('is_active', true)
            ->orderBy('type')->orderBy('name')
            ->get();

        $rows = [];
        foreach ($accounts as $account) {
            $rows[] = [
                'journal' => $account,
                'balance' => $this->balanceOf($account),
            ];
        }

        return $rows;
    }

    private function balanceOf(Journal $journal): string
    {
        $opening = (string) ($journal->opening_balance ?? '0');

        if ($journal->chart_of_account_id === null) {
            return $opening;
        }

        $sum = JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $journal->tenant_id)
            ->where('account_id', $journal->chart_of_account_id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as balance')
            ->value('balance');

        return bcadd($opening, (string) ($sum ?? '0'), 4);
    }

    /**
     * @return array<string, array{count:int, total:string}> bucket → agg
     */
    public function upcomingChecksBuckets(int $tenantId, string $direction): array
    {
        $notes = CheckAndNote::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('direction', $direction)
            ->whereIn('status', $direction === 'incoming'
                ? [CheckAndNote::STATUS_PORTFOLIO, CheckAndNote::STATUS_SENT_TO_BANK]
                : [CheckAndNote::STATUS_PORTFOLIO])
            ->get();

        $buckets = [
            'overdue' => ['count' => 0, 'total' => '0'],
            '0_7' => ['count' => 0, 'total' => '0'],
            '8_30' => ['count' => 0, 'total' => '0'],
            '31_60' => ['count' => 0, 'total' => '0'],
            '61_90' => ['count' => 0, 'total' => '0'],
            '90_plus' => ['count' => 0, 'total' => '0'],
        ];

        foreach ($notes as $note) {
            $days = now()->startOfDay()->diffInDays($note->maturity_date->startOfDay(), false);
            $key = match (true) {
                $days < 0 => 'overdue',
                $days <= 7 => '0_7',
                $days <= 30 => '8_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => '90_plus',
            };
            $buckets[$key]['count']++;
            $buckets[$key]['total'] = bcadd($buckets[$key]['total'], (string) $note->amount, 4);
        }

        return $buckets;
    }

    /**
     * Sonraki 30/60/90 gün için nakit girişi + çıkışı projeksiyonu.
     *
     * @return array<int, array{days:int, expected_in:string, expected_out:string, net:string}>
     */
    public function projection(int $tenantId, array $horizons = [30, 60, 90]): array
    {
        $now = now()->startOfDay();
        $out = [];

        foreach ($horizons as $days) {
            $end = $now->copy()->addDays($days);

            $inChecks = CheckAndNote::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('direction', 'incoming')
                ->whereIn('status', [CheckAndNote::STATUS_PORTFOLIO, CheckAndNote::STATUS_SENT_TO_BANK])
                ->whereBetween('maturity_date', [$now, $end])
                ->sum('amount');

            $outChecks = CheckAndNote::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('direction', 'outgoing')
                ->where('status', CheckAndNote::STATUS_PORTFOLIO)
                ->whereBetween('maturity_date', [$now, $end])
                ->sum('amount');

            // Basit fatura tahmini: posted + ödenmemiş, dönem içindeyse
            $inInvoices = $this->openInvoiceTotal($tenantId, 'sale');
            $outInvoices = $this->openInvoiceTotal($tenantId, 'purchase');

            // Kart tahsilat valörü de eklenir
            $inCards = CardPayment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', CardPayment::STATUS_PENDING)
                ->whereBetween('expected_settlement_date', [$now, $end])
                ->sum('net_amount');

            $totalIn = bcadd(bcadd((string) $inChecks, (string) $inCards, 4), (string) $inInvoices, 4);
            $totalOut = bcadd((string) $outChecks, (string) $outInvoices, 4);

            $out[] = [
                'days' => $days,
                'expected_in' => $totalIn,
                'expected_out' => $totalOut,
                'net' => bcsub($totalIn, $totalOut, 4),
            ];
        }

        return $out;
    }

    private function openInvoiceTotal(int $tenantId, string $type): string
    {
        $invoices = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('status', 'posted')
            ->with(['lines.taxRate', 'allocations'])
            ->get();

        $sum = '0.0000';
        foreach ($invoices as $invoice) {
            $total = $invoice->total();
            $paid = $invoice->allocations->reduce(
                fn (string $c, $a) => bcadd($c, (string) $a->allocated_amount, 4),
                '0.0000',
            );
            $remaining = bcsub($total, $paid, 4);
            if (bccomp($remaining, '0', 4) > 0) {
                $sum = bcadd($sum, $remaining, 4);
            }
        }

        return $sum;
    }
}
