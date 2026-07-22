<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
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

            return $this->write(
                tenantId: $invoice->tenant_id,
                journalType: 'purchase',
                entryDate: now()->toDateString(),
                reference: $invoice,
                lines: [
                    ['account_id' => $incomeTax->id, 'debit' => $tax, 'credit' => '0.0000'],
                    ['account_id' => $payables->id, 'debit' => '0.0000', 'credit' => $tax],
                ],
            );
        }

        $receivables = $this->defaults->accountByCode($invoice->tenant_id, '120');
        $outputTax = $this->defaults->accountByCode($invoice->tenant_id, '391');
        $total = $invoice->total();

        $lines = [
            ['account_id' => $receivables->id, 'debit' => $total, 'credit' => '0.0000'],
        ];

        foreach ($invoice->lines as $line) {
            $category = $this->categoryFor($line);
            abort_if($category === null || $category->income_account_id === null, 422, __('This product\'s category has no income account configured.'));
            $lines[] = ['account_id' => $category->income_account_id, 'debit' => '0.0000', 'credit' => $line->subtotal()];
        }

        if (bccomp($tax, '0', 4) > 0) {
            $lines[] = ['account_id' => $outputTax->id, 'debit' => '0.0000', 'credit' => $tax];
        }

        return $this->write(
            tenantId: $invoice->tenant_id,
            journalType: 'sale',
            entryDate: now()->toDateString(),
            reference: $invoice,
            lines: $lines,
        );
    }

    private function categoryFor(InvoiceLine $line): ?ProductCategory
    {
        $product = Product::withoutGlobalScopes()->find($line->product_id);

        return $product?->product_category_id !== null
            ? ProductCategory::withoutGlobalScopes()->find($product->product_category_id)
            : null;
    }
}
