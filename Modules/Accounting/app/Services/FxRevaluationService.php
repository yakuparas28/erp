<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\FxRevaluation;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

/**
 * Kambiyo farkı muhasebeleşmesi (PRD 3.13 YENİ KURAL). Gerçekleşen fark,
 * JournalEntryService::calculateFxDifferenceTL()'den (TEK KAYNAK) alınır —
 * bu, yevmiye kaydının 646/656 satırına yazdığı AYNI TL tutarıdır. Böylece
 * fx_revaluations.difference_amount ile yevmiye kaydı HER ZAMAN birebir
 * tutarlı kalır (iki bağımsız hesaplamanın bcmath kesme sırası farkından
 * ±0.0001 TL sapması engellenir). Satışta pozitif fark kâr (646), alışta
 * pozitif fark zarar (656) — yön ters, çünkü satışta alacağın değeri
 * artması kâr, alışta borcun değeri artması zarardır.
 */
class FxRevaluationService
{
    public function __construct(private readonly JournalEntryService $journalEntries) {}

    public function recognizeRealized(Payment $payment, Invoice $invoice, string $allocatedAmount): ?FxRevaluation
    {
        if ($invoice->currency_id === null) {
            return null;
        }

        $rawDifference = $this->journalEntries->calculateFxDifferenceTL($payment, $invoice, $allocatedAmount);

        if (bccomp($rawDifference, '0', 4) === 0) {
            return null;
        }

        $signedDifference = $invoice->type === 'sale' ? $rawDifference : bcmul($rawDifference, '-1', 4);

        $revaluation = new FxRevaluation([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'type' => 'realized',
            'difference_amount' => $signedDifference,
            'revaluation_date' => $payment->payment_date->toDateString(),
        ]);
        $revaluation->tenant_id = $payment->tenant_id;
        $revaluation->save();

        return $revaluation;
    }
}
