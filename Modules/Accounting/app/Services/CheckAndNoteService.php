<?php

namespace Modules\Accounting\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\CheckAndNote;
use Modules\Accounting\Models\Journal;

/**
 * Çek/Senet portföy servisi. Status makinesi + her transition için
 * otomatik JE üretimi. Muhasebe hesap kodları (TR Tekdüzen):
 *   101 Alınan Çekler
 *   103 Verilen Çekler ve Ödeme Emirleri (-)
 *   108 Diğer Hazır Değerler (bankadaki tahsildeki çekler)
 *   120 Alıcılar
 *   121 Alacak Senetleri
 *   320 Satıcılar
 *   321 Borç Senetleri
 */
class CheckAndNoteService
{
    public function __construct(
        private readonly JournalEntryService $journalEntries,
        private readonly AccountingDefaultsService $defaults,
    ) {}

    public function register(array $data, User $creator): CheckAndNote
    {
        $note = new CheckAndNote(array_merge($data, [
            'status' => $data['direction'] === CheckAndNote::DIR_INCOMING
                ? CheckAndNote::STATUS_PORTFOLIO
                : CheckAndNote::STATUS_PORTFOLIO, // outgoing da başlangıçta "portföyde" (yani verilmek üzere hazır)
            'status_changed_at' => now()->toDateString(),
            'created_by' => $creator->id,
        ]));
        $note->tenant_id = $creator->tenant_id;
        $note->save();

        // Kayıt anında JE: dr çek/senet hesabı, cr kontrol hesabı (120/320)
        $noteAccountCode = $this->noteAccountCode($note);
        $controlCode = $note->isIncoming() ? '120' : '320';

        return DB::transaction(function () use ($note, $noteAccountCode, $controlCode) {
            $noteAccount = $this->defaults->accountByCode($note->tenant_id, $noteAccountCode);
            $controlAccount = $this->defaults->accountByCode($note->tenant_id, $controlCode);

            if ($note->isIncoming()) {
                // Alınan çek/senet: dr 101/121, cr 120 (müşteriden alacak azalır)
                $this->postEntry($note, [
                    ['account_id' => $noteAccount->id, 'debit' => $note->amount, 'credit' => '0.0000'],
                    ['account_id' => $controlAccount->id, 'debit' => '0.0000', 'credit' => $note->amount],
                ]);
            } else {
                // Verilen çek/senet: dr 320 (borç azalır), cr 103/321 (yeni yükümlülük)
                $this->postEntry($note, [
                    ['account_id' => $controlAccount->id, 'debit' => $note->amount, 'credit' => '0.0000'],
                    ['account_id' => $noteAccount->id, 'debit' => '0.0000', 'credit' => $note->amount],
                ]);
            }

            return $note->fresh();
        });
    }

    /**
     * Alınan çeki başka bir tedarikçiye ciro et. dr 320 (borç azaltır),
     * cr 101/121 (portföyden düşer).
     */
    public function endorse(CheckAndNote $note, int $toPartnerId): CheckAndNote
    {
        abort_unless($note->isIncoming(), 422, __('Only incoming instruments can be endorsed.'));
        abort_unless($note->status === CheckAndNote::STATUS_PORTFOLIO, 422, __('Only portfolio instruments can be endorsed.'));

        return DB::transaction(function () use ($note, $toPartnerId) {
            $noteAccount = $this->defaults->accountByCode($note->tenant_id, $this->noteAccountCode($note));
            $payables = $this->defaults->accountByCode($note->tenant_id, '320');

            $this->postEntry($note, [
                ['account_id' => $payables->id, 'debit' => $note->amount, 'credit' => '0.0000'],
                ['account_id' => $noteAccount->id, 'debit' => '0.0000', 'credit' => $note->amount],
            ]);

            $note->update([
                'status' => CheckAndNote::STATUS_ENDORSED,
                'endorsed_to_partner_id' => $toPartnerId,
                'status_changed_at' => now()->toDateString(),
            ]);

            return $note->fresh();
        });
    }

    /**
     * Alınan çeki bankaya tahsile ver. dr 108 (tahsildeki çekler),
     * cr 101/121 (portföyden düşer).
     */
    public function sendToBank(CheckAndNote $note, int $bankJournalId): CheckAndNote
    {
        abort_unless($note->isIncoming(), 422, __('Only incoming instruments can be sent to a bank.'));
        abort_unless($note->status === CheckAndNote::STATUS_PORTFOLIO, 422, __('Only portfolio instruments can be sent to a bank.'));

        $bankJournal = Journal::withoutGlobalScopes()->findOrFail($bankJournalId);
        abort_unless($bankJournal->type === 'bank' && $bankJournal->tenant_id === $note->tenant_id, 422, __('Invalid bank journal.'));

        return DB::transaction(function () use ($note, $bankJournalId) {
            $noteAccount = $this->defaults->accountByCode($note->tenant_id, $this->noteAccountCode($note));
            $transit = $this->defaults->accountByCode($note->tenant_id, '108');

            $this->postEntry($note, [
                ['account_id' => $transit->id, 'debit' => $note->amount, 'credit' => '0.0000'],
                ['account_id' => $noteAccount->id, 'debit' => '0.0000', 'credit' => $note->amount],
            ]);

            $note->update([
                'status' => CheckAndNote::STATUS_SENT_TO_BANK,
                'collection_bank_journal_id' => $bankJournalId,
                'status_changed_at' => now()->toDateString(),
            ]);

            return $note->fresh();
        });
    }

    /**
     * Alınan çekin bankaya tahsil edildiğini işaretle. Doğrudan portföyden
     * de gelebilir (küçük firmalar bankaya vermeden bekletirler). Her iki
     * durumda hedef aynı: dr 102/100 (banka/kasa), cr 101/121 veya 108.
     */
    public function markCollected(CheckAndNote $note, int $bankOrCashJournalId): CheckAndNote
    {
        abort_unless($note->isIncoming(), 422, __('Only incoming instruments can be collected.'));
        abort_unless(
            in_array($note->status, [CheckAndNote::STATUS_PORTFOLIO, CheckAndNote::STATUS_SENT_TO_BANK], true),
            422,
            __('This instrument cannot be collected in its current status.'),
        );

        $journal = Journal::withoutGlobalScopes()->findOrFail($bankOrCashJournalId);
        abort_unless(in_array($journal->type, ['cash', 'bank'], true) && $journal->tenant_id === $note->tenant_id, 422, __('Invalid journal.'));

        return DB::transaction(function () use ($note, $journal) {
            $cashOrBankAccount = $journal->chart_of_account_id !== null
                ? ChartOfAccount::withoutGlobalScopes()->findOrFail($journal->chart_of_account_id)
                : $this->defaults->accountByCode($note->tenant_id, $journal->type === 'cash' ? '100' : '102');

            $sourceCode = $note->status === CheckAndNote::STATUS_SENT_TO_BANK ? '108' : $this->noteAccountCode($note);
            $sourceAccount = $this->defaults->accountByCode($note->tenant_id, $sourceCode);

            $this->postEntry($note, [
                ['account_id' => $cashOrBankAccount->id, 'debit' => $note->amount, 'credit' => '0.0000'],
                ['account_id' => $sourceAccount->id, 'debit' => '0.0000', 'credit' => $note->amount],
            ]);

            $note->update([
                'status' => CheckAndNote::STATUS_COLLECTED,
                'collection_bank_journal_id' => $journal->id,
                'status_changed_at' => now()->toDateString(),
            ]);

            return $note->fresh();
        });
    }

    /**
     * Alınan çek karşılıksız çıktı. dr 120 (alıcıya iade), cr 101/121 veya 108.
     */
    public function markBounced(CheckAndNote $note): CheckAndNote
    {
        abort_unless($note->isIncoming(), 422, __('Only incoming instruments can bounce.'));
        abort_unless(
            in_array($note->status, [CheckAndNote::STATUS_PORTFOLIO, CheckAndNote::STATUS_SENT_TO_BANK], true),
            422,
            __('This instrument cannot bounce in its current status.'),
        );

        return DB::transaction(function () use ($note) {
            $receivables = $this->defaults->accountByCode($note->tenant_id, '120');
            $sourceCode = $note->status === CheckAndNote::STATUS_SENT_TO_BANK ? '108' : $this->noteAccountCode($note);
            $sourceAccount = $this->defaults->accountByCode($note->tenant_id, $sourceCode);

            $this->postEntry($note, [
                ['account_id' => $receivables->id, 'debit' => $note->amount, 'credit' => '0.0000'],
                ['account_id' => $sourceAccount->id, 'debit' => '0.0000', 'credit' => $note->amount],
            ]);

            $note->update([
                'status' => CheckAndNote::STATUS_BOUNCED,
                'status_changed_at' => now()->toDateString(),
            ]);

            return $note->fresh();
        });
    }

    /**
     * Verilen çeki ödendi işaretle. dr 103/321, cr 102/100 (banka/kasa).
     */
    public function markPaid(CheckAndNote $note, int $bankOrCashJournalId): CheckAndNote
    {
        abort_if($note->isIncoming(), 422, __('Only outgoing instruments can be marked paid.'));
        abort_unless($note->status === CheckAndNote::STATUS_PORTFOLIO, 422, __('This instrument cannot be marked paid in its current status.'));

        $journal = Journal::withoutGlobalScopes()->findOrFail($bankOrCashJournalId);
        abort_unless(in_array($journal->type, ['cash', 'bank'], true) && $journal->tenant_id === $note->tenant_id, 422, __('Invalid journal.'));

        return DB::transaction(function () use ($note, $journal) {
            $cashOrBankAccount = $journal->chart_of_account_id !== null
                ? ChartOfAccount::withoutGlobalScopes()->findOrFail($journal->chart_of_account_id)
                : $this->defaults->accountByCode($note->tenant_id, $journal->type === 'cash' ? '100' : '102');

            $noteAccount = $this->defaults->accountByCode($note->tenant_id, $this->noteAccountCode($note));

            $this->postEntry($note, [
                ['account_id' => $noteAccount->id, 'debit' => $note->amount, 'credit' => '0.0000'],
                ['account_id' => $cashOrBankAccount->id, 'debit' => '0.0000', 'credit' => $note->amount],
            ]);

            $note->update([
                'status' => CheckAndNote::STATUS_PAID,
                'collection_bank_journal_id' => $journal->id,
                'status_changed_at' => now()->toDateString(),
            ]);

            return $note->fresh();
        });
    }

    private function noteAccountCode(CheckAndNote $note): string
    {
        return match (true) {
            $note->isIncoming() && $note->instrument_type === CheckAndNote::TYPE_CHECK => '101',
            $note->isIncoming() && $note->instrument_type === CheckAndNote::TYPE_NOTE => '121',
            ! $note->isIncoming() && $note->instrument_type === CheckAndNote::TYPE_CHECK => '103',
            default => '321',
        };
    }

    private function postEntry(CheckAndNote $note, array $lines): void
    {
        $this->journalEntries->write(
            tenantId: $note->tenant_id,
            journalType: 'general',
            entryDate: now()->toDateString(),
            reference: $note,
            lines: $lines,
        );
    }
}
