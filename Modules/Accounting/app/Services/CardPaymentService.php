<?php

namespace Modules\Accounting\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\CardPayment;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\PosTerminal;

/**
 * POS/Kredi Kartı tahsilat servisi.
 *
 * record(): kart tahsilatı yapıldı — henüz banka hesabına düşmedi. JE:
 *   dr 108 (Bloke, brüt), cr 120 (müşteriden alacak düşer, brüt)
 *
 * settle(): valör geldi, banka hesabına düştü — net tutar bankaya, komisyon
 * gider hesabına, blokeli hesap kapanır. JE:
 *   dr 102 (banka, net), dr 653 (komisyon gideri), cr 108 (blokeden çıktı, brüt)
 */
class CardPaymentService
{
    public function __construct(
        private readonly JournalEntryService $journalEntries,
        private readonly AccountingDefaultsService $defaults,
    ) {}

    public function record(PosTerminal $terminal, int $partnerId, string $grossAmount, int $installments, string $transactionDate, User $creator): CardPayment
    {
        $rate = $terminal->rateFor($installments);
        $commission = bcdiv(bcmul($grossAmount, $rate, 6), '100', 4);
        $net = bcsub($grossAmount, $commission, 4);

        $settlementDate = CarbonImmutable::parse($transactionDate)
            ->addDays($terminal->settlement_days ?? 1)
            ->toDateString();

        return DB::transaction(function () use ($terminal, $partnerId, $grossAmount, $installments, $transactionDate, $creator, $rate, $commission, $net, $settlementDate) {
            $card = new CardPayment([
                'pos_terminal_id' => $terminal->id,
                'partner_id' => $partnerId,
                'gross_amount' => $grossAmount,
                'installments' => $installments,
                'commission_rate' => $rate,
                'commission_amount' => $commission,
                'net_amount' => $net,
                'transaction_date' => $transactionDate,
                'expected_settlement_date' => $settlementDate,
                'status' => CardPayment::STATUS_PENDING,
                'created_by' => $creator->id,
            ]);
            $card->tenant_id = $creator->tenant_id;
            $card->save();

            $blockedAccount = $this->defaults->accountByCode($card->tenant_id, '108');
            $receivables = $this->defaults->accountByCode($card->tenant_id, '120');

            $this->journalEntries->write(
                tenantId: $card->tenant_id,
                journalType: 'general',
                entryDate: $transactionDate,
                reference: $card,
                lines: [
                    ['account_id' => $blockedAccount->id, 'debit' => $grossAmount, 'credit' => '0.0000'],
                    ['account_id' => $receivables->id, 'debit' => '0.0000', 'credit' => $grossAmount],
                ],
            );

            return $card->fresh();
        });
    }

    public function settle(CardPayment $card, ?string $actualDate = null): CardPayment
    {
        abort_unless($card->status === CardPayment::STATUS_PENDING, 422, __('This card payment is not pending settlement.'));

        $terminal = $card->terminal()->with('bankJournal')->firstOrFail();
        $journal = $terminal->bankJournal;
        abort_if($journal === null, 422, __('This POS terminal has no bank journal linked.'));

        return DB::transaction(function () use ($card, $journal, $actualDate) {
            $bankAccount = $journal->chart_of_account_id !== null
                ? ChartOfAccount::withoutGlobalScopes()->findOrFail($journal->chart_of_account_id)
                : $this->defaults->accountByCode($card->tenant_id, '102');

            $blocked = $this->defaults->accountByCode($card->tenant_id, '108');
            $commissionExpense = $this->defaults->accountByCode($card->tenant_id, '653');

            $lines = [
                ['account_id' => $bankAccount->id, 'debit' => $card->net_amount, 'credit' => '0.0000'],
                ['account_id' => $blocked->id, 'debit' => '0.0000', 'credit' => $card->gross_amount],
            ];
            if (bccomp($card->commission_amount, '0', 4) > 0) {
                $lines[] = ['account_id' => $commissionExpense->id, 'debit' => $card->commission_amount, 'credit' => '0.0000'];
            }

            $this->journalEntries->write(
                tenantId: $card->tenant_id,
                journalType: 'bank',
                entryDate: $actualDate ?? now()->toDateString(),
                reference: $card,
                lines: $lines,
            );

            $card->update([
                'status' => CardPayment::STATUS_SETTLED,
                'settled_at' => $actualDate ?? now()->toDateString(),
            ]);

            return $card->fresh();
        });
    }
}
