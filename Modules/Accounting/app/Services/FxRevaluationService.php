<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\FxRevaluation;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

/**
 * Kambiyo farkı muhasebeleşmesi (PRD 3.13 YENİ KURAL). Gerçekleşen fark =
 * (ödeme kuru - fatura kuru) × dağıtılan tutar; satışta pozitif fark kâr
 * (646), alışta pozitif fark zarar (656) — yön ters, çünkü satışta
 * alacağın değeri artması kâr, alışta borcun değeri artması zarardır.
 */
class FxRevaluationService
{
    public function recognizeRealized(Payment $payment, Invoice $invoice, string $allocatedAmount): ?FxRevaluation
    {
        if ($invoice->currency_id === null) {
            return null;
        }

        $rateDifference = bcsub($payment->exchangeRateOrOne(), $invoice->exchangeRateOrOne(), 6);

        if (bccomp($rateDifference, '0', 6) === 0) {
            return null;
        }

        $rawDifference = bcmul($allocatedAmount, $rateDifference, 4);
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
