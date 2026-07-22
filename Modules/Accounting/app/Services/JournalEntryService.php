<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\StockMove;
use Modules\Purchase\Models\PurchaseOrderLine;

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
}
