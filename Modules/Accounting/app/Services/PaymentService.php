<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Models\PaymentAllocation;

/**
 * Tahsilat/ödeme (PRD 3.12): payment_allocations üzerinden bir veya
 * birden fazla faturaya kısmi dağıtım. Bir fatura tam kapanınca
 * invoices.status='paid' olur.
 */
class PaymentService
{
    public function __construct(
        private readonly JournalEntryService $journalEntries,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function create(int $tenantId, int $partnerId, int $journalId, string $amount, string $paymentDate, ?int $currencyId = null): Payment
    {
        $journal = Journal::withoutGlobalScopes()->findOrFail($journalId);
        abort_unless(in_array($journal->type, ['cash', 'bank'], true), 422, __('Payments must use a cash or bank journal.'));

        $payment = new Payment([
            'partner_id' => $partnerId,
            'journal_id' => $journalId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'currency_id' => $currencyId,
            'exchange_rate_used' => $currencyId !== null
                ? $this->exchangeRates->lockRateFor($tenantId, $currencyId, $paymentDate)
                : null,
        ]);
        $payment->tenant_id = $tenantId;
        $payment->save();

        return $payment;
    }

    public function allocate(Payment $payment, Invoice $invoice, string $amount): PaymentAllocation
    {
        abort_if(bccomp($amount, $payment->unallocatedAmount(), 4) > 0, 422, __('This amount exceeds the unallocated payment balance.'));
        abort_if(bccomp($amount, $invoice->remainingBalance(), 4) > 0, 422, __('This amount exceeds the invoice\'s remaining balance.'));

        $allocation = new PaymentAllocation([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => $amount,
        ]);
        $allocation->tenant_id = $payment->tenant_id;
        $allocation->save();

        $this->journalEntries->postForPayment($payment, $invoice, $amount);

        if (bccomp($invoice->fresh()->remainingBalance(), '0', 4) === 0) {
            $invoice->update(['status' => 'paid']);
        }

        return $allocation;
    }
}
