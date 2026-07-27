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
    public function __construct(
        private readonly JournalEntryService $journalEntries,
        private readonly ExchangeRateService $exchangeRates,
        private readonly AccountingDefaultsService $defaults,
    ) {}

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

    /**
     * Dönem sonu değerleme (PRD 3.13): açık (henüz tam ödenmemiş) döviz
     * faturalarının kalan bakiyesi, güncel TCMB kuruyla yeniden değerlenir.
     * invoice.exchange_rate_used DEĞİŞMEZ — yalnızca raporlama amaçlı bir
     * kayıt üretilir (bilinçli sadeleştirme: dönem başı ters kayıt/reversal
     * bu fazın kapsamında değil).
     *
     * UYARI — bu metod ŞU AN hiçbir ekran/komuttan ÇAĞRILMIYOR (yalnızca
     * testlerden). Üretim koduna bağlanmadan önce şu iki kısıt giderilmeli:
     * (1) ürettiği kayıt bir sonraki dönem başında TERS KAYITLA geri
     * alınmıyor — bu olmadan 120/320 bakiyesi kalıcı olarak bozulur ve
     * fatura daha sonra ödendiğinde tam kapanmaz; (2) aynı asOfDate için
     * tekrar çağrılmaya karşı bir idempotency kontrolü YOK (çift kayıt
     * riski). Bu iki eksik, bu metodu gerçek bir dönem-sonu iş akışına
     * bağlamadan ÖNCE (muhtemelen Faz 11+ ekran/otomasyon işinde) ele
     * alınmalıdır.
     *
     * @return list<FxRevaluation>
     */
    public function revaluateOpenBalances(int $tenantId, string $asOfDate): array
    {
        $invoices = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'posted')
            ->whereNotNull('currency_id')
            ->get()
            ->filter(fn (Invoice $invoice) => bccomp($invoice->remainingBalance(), '0', 4) > 0);

        $created = [];

        foreach ($invoices as $invoice) {
            $currentRate = $this->exchangeRates->lockRateFor($tenantId, $invoice->currency_id, $asOfDate);
            $rawDifference = $this->calculateUnrealizedDifferenceTL($currentRate, $invoice);

            if (bccomp($rawDifference, '0', 4) === 0) {
                continue;
            }

            $signedDifference = $invoice->type === 'sale' ? $rawDifference : bcmul($rawDifference, '-1', 4);

            $revaluation = new FxRevaluation([
                'invoice_id' => $invoice->id,
                'payment_id' => null,
                'type' => 'unrealized',
                'difference_amount' => $signedDifference,
                'revaluation_date' => $asOfDate,
            ]);
            $revaluation->tenant_id = $tenantId;
            $revaluation->save();

            $isGain = bccomp($signedDifference, '0', 4) > 0;
            $account = $this->defaults->accountByCode($tenantId, $isGain ? '646' : '656');
            $controlAccount = $this->defaults->accountByCode($tenantId, $invoice->type === 'purchase' ? '320' : '120');
            $absDifference = $isGain ? $signedDifference : bcmul($signedDifference, '-1', 4);

            $this->journalEntries->write(
                tenantId: $tenantId,
                journalType: 'general',
                entryDate: $asOfDate,
                reference: $invoice,
                lines: $isGain
                    ? [
                        ['account_id' => $controlAccount->id, 'debit' => $absDifference, 'credit' => '0.0000'],
                        ['account_id' => $account->id, 'debit' => '0.0000', 'credit' => $absDifference],
                    ]
                    : [
                        ['account_id' => $account->id, 'debit' => $absDifference, 'credit' => '0.0000'],
                        ['account_id' => $controlAccount->id, 'debit' => '0.0000', 'credit' => $absDifference],
                    ],
            );

            $created[] = $revaluation;
        }

        return $created;
    }

    /**
     * Kalan bakiyenin güncel kur ile fatura kuru arasındaki TL farkını
     * hesaplar: ÖNCE iki ayrı bcmul() 4 ondalık basamağa kesilir, SONRA
     * çıkarılır — calculateFxDifferenceTL()'deki İLE AYNI sıra (Task 4'te
     * keşfedilen bcmath kesme sırası tutarsızlığını burada da önlemek için;
     * bkz. sınıf docblock'u).
     */
    private function calculateUnrealizedDifferenceTL(string $currentRate, Invoice $invoice): string
    {
        $remainingBalance = $invoice->remainingBalance();

        return bcsub(
            bcmul($remainingBalance, $currentRate, 4),
            bcmul($remainingBalance, $invoice->exchangeRateOrOne(), 4),
            4,
        );
    }
}
