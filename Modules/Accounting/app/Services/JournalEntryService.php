<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Models\Payment;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\StockMove;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Sales\Models\SalesOrderLine;

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

    /**
     * Satınalma teslim alımı (PRD 3.12): net mal değeri kadar
     * accounting_mode'a göre stok(anglo)/gider(continental) hesabına
     * borç, Satıcılar(320)'a alacak. Ürün kategorisi veya ilgili hesap
     * tanımlı değilse (Muhasebe henüz yapılandırılmamış tenant) SESSİZCE
     * atlanır — satınalma akışını asla bloke etmez.
     */
    public function postForPurchaseReceipt(PurchaseOrderLine $line, StockMove $move): ?JournalEntry
    {
        $product = Product::withoutGlobalScopes()->find($line->product_id);
        $category = $product?->product_category_id !== null
            ? ProductCategory::withoutGlobalScopes()->find($product->product_category_id)
            : null;

        if ($category === null) {
            return null;
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($line->tenant_id);
        $value = bcmul($move->qty, $line->unit_price, 4);

        $debitAccountId = $tenant->accounting_mode === 'continental'
            ? $category->expense_account_id
            : $category->stock_input_account_id;

        if ($debitAccountId === null) {
            return null;
        }

        $payables = $this->defaults->accountByCode($line->tenant_id, '320');

        return $this->write(
            tenantId: $line->tenant_id,
            journalType: 'purchase',
            entryDate: now()->toDateString(),
            reference: $move,
            lines: [
                ['account_id' => $debitAccountId, 'debit' => $value, 'credit' => '0.0000'],
                ['account_id' => $payables->id, 'debit' => '0.0000', 'credit' => $value],
            ],
        );
    }

    /**
     * Satış teslimatı (PRD 3.11/3.12): yalnızca anglo_saxon modda COGS'u
     * dr expense(621) / cr stock_output(153) olarak tanır — continental
     * modda COGS zaten alım anında tanındığından burada İKİNCİ KEZ
     * tanınmaz (no-op, null döner). Kategori/hesap eksikse de sessizce
     * atlanır (Task 4'teki gerekçeyle aynı).
     */
    public function postForSalesDelivery(SalesOrderLine $line, StockMove $move, string $cogsAmount): ?JournalEntry
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($line->tenant_id);

        if ($tenant->accounting_mode === 'continental') {
            return null;
        }

        if (bccomp($cogsAmount, '0', 4) <= 0) {
            return null;
        }

        // NOT: $move->product_id kullanılır (kit satırında $line->product_id kit
        // ürününe, $move ise gerçek bileşene aittir — bkz. Task 5 brief notu).
        $product = Product::withoutGlobalScopes()->find($move->product_id);
        $category = $product?->product_category_id !== null
            ? ProductCategory::withoutGlobalScopes()->find($product->product_category_id)
            : null;

        if ($category === null || $category->expense_account_id === null || $category->stock_output_account_id === null) {
            return null;
        }

        return $this->write(
            tenantId: $line->tenant_id,
            journalType: 'sale',
            entryDate: now()->toDateString(),
            reference: $move,
            lines: [
                ['account_id' => $category->expense_account_id, 'debit' => $cogsAmount, 'credit' => '0.0000'],
                ['account_id' => $category->stock_output_account_id, 'debit' => '0.0000', 'credit' => $cogsAmount],
            ],
        );
    }

    /**
     * Fatura onayı (PRD 3.12). Satınalma faturası: net mal değeri
     * receive()'de ZATEN kaydedildiğinden (Task 4) burada yalnızca KDV
     * tutarı Satıcılar'a (320) eklenir. Satış faturası: gelir tanıma HER
     * ZAMAN burada olur (teslimatta değil) — dr Alıcılar(120, brüt) /
     * cr income_account_id(net) / cr Hesaplanan KDV(391, KDV).
     */
    public function postForInvoice(Invoice $invoice): JournalEntry
    {
        $tax = $invoice->taxTotal();

        if ($invoice->type === 'purchase') {
            $incomeTax = $this->defaults->accountByCode($invoice->tenant_id, '191');
            $payables = $this->defaults->accountByCode($invoice->tenant_id, '320');

            abort_if(bccomp($tax, '0', 4) <= 0, 422, __('This invoice has no tax amount to post.'));

            $taxTL = bcmul($tax, $invoice->exchangeRateOrOne(), 4);

            return $this->write(
                tenantId: $invoice->tenant_id,
                journalType: 'purchase',
                entryDate: now()->toDateString(),
                reference: $invoice,
                lines: [
                    ['account_id' => $incomeTax->id, 'debit' => $taxTL, 'credit' => '0.0000'],
                    ['account_id' => $payables->id, 'debit' => '0.0000', 'credit' => $taxTL],
                ],
            );
        }

        $receivables = $this->defaults->accountByCode($invoice->tenant_id, '120');
        $outputTax = $this->defaults->accountByCode($invoice->tenant_id, '391');
        $totalTL = bcmul($invoice->total(), $invoice->exchangeRateOrOne(), 4);

        $lines = [
            ['account_id' => $receivables->id, 'debit' => $totalTL, 'credit' => '0.0000'],
        ];

        foreach ($invoice->lines as $line) {
            $category = $this->categoryFor($line);
            abort_if($category === null || $category->income_account_id === null, 422, __('This product\'s category has no income account configured.'));
            $lines[] = ['account_id' => $category->income_account_id, 'debit' => '0.0000', 'credit' => bcmul($line->subtotal(), $invoice->exchangeRateOrOne(), 4)];
        }

        if (bccomp($tax, '0', 4) > 0) {
            $lines[] = ['account_id' => $outputTax->id, 'debit' => '0.0000', 'credit' => bcmul($tax, $invoice->exchangeRateOrOne(), 4)];
        }

        return $this->write(
            tenantId: $invoice->tenant_id,
            journalType: 'sale',
            entryDate: now()->toDateString(),
            reference: $invoice,
            lines: $lines,
        );
    }

    /**
     * Ödeme/tahsilat kaydı (PRD 3.12): tedarikçiye ödeme → dr Satıcılar(320)
     * / cr kasa-banka; müşteriden tahsilat → dr kasa-banka / cr Alıcılar(120).
     * Kasa/banka hesabı journal'ın type'ına göre kod üzerinden çözülür
     * (cash→100, bank→102).
     */
    public function postForPayment(Payment $payment, Invoice $invoice, string $amount): JournalEntry
    {
        // Journal'a özel hesap tanımlıysa onu kullan (ör. "Vakıfbank TL" =
        // 102.03). Aksi hâlde eski davranış: '100' (kasa) / '102' (banka)
        // varsayılan hesabı.
        if ($payment->journal->chart_of_account_id !== null) {
            $cashOrBank = ChartOfAccount::withoutGlobalScopes()
                ->findOrFail($payment->journal->chart_of_account_id);
        } else {
            $cashOrBankCode = $payment->journal->type === 'cash' ? '100' : '102';
            $cashOrBank = $this->defaults->accountByCode($payment->tenant_id, $cashOrBankCode);
        }
        $controlAccount = $this->defaults->accountByCode($payment->tenant_id, $invoice->type === 'purchase' ? '320' : '120');

        $controlAmountTL = bcmul($amount, $invoice->exchangeRateOrOne(), 4);
        $cashAmountTL = bcmul($amount, $payment->exchangeRateOrOne(), 4);
        $fxDifference = $this->calculateFxDifferenceTL($payment, $invoice, $amount);

        $lines = $invoice->type === 'purchase'
            ? [
                ['account_id' => $controlAccount->id, 'debit' => $controlAmountTL, 'credit' => '0.0000'],
                ['account_id' => $cashOrBank->id, 'debit' => '0.0000', 'credit' => $cashAmountTL],
            ]
            : [
                ['account_id' => $cashOrBank->id, 'debit' => $cashAmountTL, 'credit' => '0.0000'],
                ['account_id' => $controlAccount->id, 'debit' => '0.0000', 'credit' => $controlAmountTL],
            ];

        if (bccomp($fxDifference, '0', 4) !== 0) {
            // satış: fark>0 → cash>control → kâr(646) fazladan kredi; fark<0 → zarar(656) fazladan borç
            // alış: fark>0 → cash>control → daha fazla ödendi → zarar(656); fark<0 → kâr(646)
            $isGainForSale = $invoice->type === 'sale' && bccomp($fxDifference, '0', 4) > 0;
            $isGainForPurchase = $invoice->type === 'purchase' && bccomp($fxDifference, '0', 4) < 0;
            $isGain = $isGainForSale || $isGainForPurchase;

            $account = $this->defaults->accountByCode($payment->tenant_id, $isGain ? '646' : '656');
            $absDifference = bccomp($fxDifference, '0', 4) < 0 ? bcmul($fxDifference, '-1', 4) : $fxDifference;

            $lines[] = ['account_id' => $account->id, 'debit' => $isGain ? '0.0000' : $absDifference, 'credit' => $isGain ? $absDifference : '0.0000'];
        }

        return $this->write(
            tenantId: $payment->tenant_id,
            journalType: $payment->journal->type,
            entryDate: $payment->payment_date->toDateString(),
            reference: $payment,
            lines: $lines,
        );
    }

    /**
     * Ödeme/tahsilatın kambiyo farkını TL cinsinden hesaplar (işaretli):
     * kasa/banka TL tutarı EKSİ kontrol hesabı TL tutarı — postForPayment()'ın
     * yevmiye satırlarına yazdığı TAM DEĞER. FxRevaluationService de AYNI
     * TEK KAYNAĞI kullanır (bkz. sınıf docblock'u) ki 646/656 satırı ile
     * fx_revaluations.difference_amount HER ZAMAN birebir tutarlı kalsın —
     * iki ayrı bcmul()'un ayrı ayrı kesilip SONRA çıkarılması, kurların
     * önce çıkarılıp TEK çarpımla kesilmesinden bcmath'in kesme (yuvarlama
     * değil) davranışı yüzünden ±0.0001 TL farklı sonuç üretebiliyordu.
     */
    public function calculateFxDifferenceTL(Payment $payment, Invoice $invoice, string $amount): string
    {
        $controlAmountTL = bcmul($amount, $invoice->exchangeRateOrOne(), 4);
        $cashAmountTL = bcmul($amount, $payment->exchangeRateOrOne(), 4);

        return bcsub($cashAmountTL, $controlAmountTL, 4);
    }

    private function categoryFor(InvoiceLine $line): ?ProductCategory
    {
        $product = Product::withoutGlobalScopes()->find($line->product_id);

        return $product?->product_category_id !== null
            ? ProductCategory::withoutGlobalScopes()->find($product->product_category_id)
            : null;
    }
}
