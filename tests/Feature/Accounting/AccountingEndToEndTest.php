<?php

namespace Tests\Feature\Accounting;

use App\Models\Tenant;
use App\Models\User;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Modules\Inventory\Models\Warehouse;
use Modules\Purchase\Services\PurchaseOrderService;
use Modules\Sales\Services\SalesOrderService;
use Tests\TenantTestCase;

/**
 * Faz 9 uçtan uca kabul kriteri: "Alım→teslim→fatura→ödeme zinciri baştan
 * sona doğru yevmiye seti üretir; mizan dengede." İki zincir (satınalma ve
 * satış) tek bir anglo_saxon tenant üzerinde, aynı ürün/hesap planıyla
 * çalıştırılır; her ikisinin de ilgili cari hesabını (320/120) net sıfıra
 * kapattığı ve tüm yevmiye satırlarının toplamda dengede olduğu doğrulanır.
 */
class AccountingEndToEndTest extends TenantTestCase
{
    private Tenant $angloTenant;

    private AccountingDefaultsService $defaults;

    private Warehouse $warehouse;

    private Location $location;

    private Uom $unit;

    private ProductCategory $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->angloTenant = Tenant::factory()->angloSaxon()->create();
        $this->defaults = app(AccountingDefaultsService::class);
        $this->defaults->provision($this->angloTenant);

        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->angloTenant->id]);
        $this->location = Location::factory()->create([
            'tenant_id' => $this->angloTenant->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->angloTenant->id]);
        $this->unit = Uom::factory()->create([
            'tenant_id' => $this->angloTenant->id,
            'uom_category_id' => $uomCategory->id,
        ]);

        $this->category = ProductCategory::factory()->create([
            'tenant_id' => $this->angloTenant->id,
            'stock_input_account_id' => $this->defaults->accountByCode($this->angloTenant->id, '153')->id,
            'stock_output_account_id' => $this->defaults->accountByCode($this->angloTenant->id, '153')->id,
            'expense_account_id' => $this->defaults->accountByCode($this->angloTenant->id, '621')->id,
            'income_account_id' => $this->defaults->accountByCode($this->angloTenant->id, '600')->id,
        ]);

        $this->product = Product::factory()->create([
            'tenant_id' => $this->angloTenant->id,
            'uom_id' => $this->unit->id,
            'cost_method' => 'fifo',
            'product_category_id' => $this->category->id,
        ]);
    }

    private function user(string $role): User
    {
        setPermissionsTeamId($this->angloTenant->id);
        $user = User::factory()->for($this->angloTenant)->create();
        $user->assignRole($role);

        return $user;
    }

    private function journalOfType(string $type): Journal
    {
        return Journal::withoutGlobalScopes()
            ->where('tenant_id', $this->angloTenant->id)->where('type', $type)->firstOrFail();
    }

    private function taxRate(string $type): TaxRate
    {
        return TaxRate::withoutGlobalScopes()
            ->where('tenant_id', $this->angloTenant->id)
            ->where('type', $type)
            ->where('percentage', '20')
            ->firstOrFail();
    }

    /**
     * Belirtilen hesap için tüm yevmiye satırlarının net bakiyesini
     * (debit toplamı - credit toplamı) bcmath ile hesaplar.
     */
    private function netDebitBalance(int $accountId): string
    {
        $totalDebit = JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $this->angloTenant->id)->where('account_id', $accountId)->sum('debit');
        $totalCredit = JournalEntryLine::withoutGlobalScopes()
            ->where('tenant_id', $this->angloTenant->id)->where('account_id', $accountId)->sum('credit');

        return bcsub((string) $totalDebit, (string) $totalCredit, 4);
    }

    private function assertTrialBalanceIsBalanced(): void
    {
        $totalDebit = JournalEntryLine::withoutGlobalScopes()->where('tenant_id', $this->angloTenant->id)->sum('debit');
        $totalCredit = JournalEntryLine::withoutGlobalScopes()->where('tenant_id', $this->angloTenant->id)->sum('credit');

        $this->assertSame(bcadd((string) $totalDebit, '0', 4), bcadd((string) $totalCredit, '0', 4));
    }

    public function test_purchase_to_payment_chain_produces_a_balanced_journal_set(): void
    {
        $payables = $this->defaults->accountByCode($this->angloTenant->id, '320');
        $stock = $this->defaults->accountByCode($this->angloTenant->id, '153');
        $inputTax = $this->defaults->accountByCode($this->angloTenant->id, '191');
        $cash = $this->defaults->accountByCode($this->angloTenant->id, '100');

        $creator = $this->user('Purchasing Officer');
        $approver = $this->user('Tenant Admin');
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->angloTenant->id]);

        $purchaseOrders = app(PurchaseOrderService::class);
        $po = $purchaseOrders->create($this->angloTenant->id, $supplier->id, $creator);
        $line = $purchaseOrders->addLine($po, $this->product->id, $this->unit->id, '10', '100.0000');
        $purchaseOrders->sendRfq($po);
        $purchaseOrders->confirm($po->fresh(), $approver);

        // Teslim alım: net mal değeri (10 * 100 = 1000.0000) dr 153 / cr 320.
        $purchaseOrders->receive($line->fresh(), '10', $this->location->id);

        $this->assertSame('1000.0000', $this->netDebitBalance($stock->id));
        $this->assertSame('-1000.0000', $this->netDebitBalance($payables->id));

        // Fatura: yalnızca KDV (%20 => 200.0000) dr 191 / cr 320.
        $accountant = $this->user('Accountant');
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create($this->angloTenant->id, $supplier->id, 'purchase', $po->fresh());
        $invoices->addLine($invoice, $this->product->id, '10', '100.0000', $this->taxRate('purchase')->id);
        $invoices->post($invoice, $accountant);

        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame('200.0000', $this->netDebitBalance($inputTax->id));
        // 320 net: -1000 (receipt) - 200 (fatura KDV) = -1200 (borç henüz kapanmadı).
        $this->assertSame('-1200.0000', $this->netDebitBalance($payables->id));

        // Ödeme: 1200.0000 tam ödeme, dr 320 / cr 100.
        $payments = app(PaymentService::class);
        $cashJournal = $this->journalOfType('cash');
        $payment = $payments->create($this->angloTenant->id, $supplier->id, $cashJournal->id, '1200.0000', now()->toDateString());
        $payments->allocate($payment, $invoice, '1200.0000');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('-1200.0000', $this->netDebitBalance($cash->id));

        // Kabul kriteri: 320 (Satıcılar) hesabının net bakiyesi sıfır — borç tamamen kapandı.
        $this->assertSame('0.0000', $this->netDebitBalance($payables->id));

        $this->assertTrialBalanceIsBalanced();
    }

    public function test_sales_to_receipt_chain_produces_a_balanced_journal_set(): void
    {
        // Bu senaryonun stoğu, önceki testten bağımsız olarak kendi teslim alımını yapar
        // (her test RefreshDatabase ile izole çalışır, ancak aynı kurulum desenini takip eder).
        $poCreator = $this->user('Purchasing Officer');
        $poApprover = $this->user('Tenant Admin');
        $supplier = Partner::factory()->supplier()->create(['tenant_id' => $this->angloTenant->id]);

        $purchaseOrders = app(PurchaseOrderService::class);
        $po = $purchaseOrders->create($this->angloTenant->id, $supplier->id, $poCreator);
        $poLine = $purchaseOrders->addLine($po, $this->product->id, $this->unit->id, '10', '100.0000');
        $purchaseOrders->sendRfq($po);
        $purchaseOrders->confirm($po->fresh(), $poApprover);
        $purchaseOrders->receive($poLine->fresh(), '10', $this->location->id);

        $receivables = $this->defaults->accountByCode($this->angloTenant->id, '120');
        $stock = $this->defaults->accountByCode($this->angloTenant->id, '153');
        $expense = $this->defaults->accountByCode($this->angloTenant->id, '621');
        $income = $this->defaults->accountByCode($this->angloTenant->id, '600');
        $outputTax = $this->defaults->accountByCode($this->angloTenant->id, '391');
        $bank = $this->defaults->accountByCode($this->angloTenant->id, '102');

        $stockBeforeDelivery = $this->netDebitBalance($stock->id);

        $soCreator = $this->user('Sales Representative');
        $soApprover = $this->user('Tenant Admin');
        $customer = Partner::factory()->customer()->create(['tenant_id' => $this->angloTenant->id]);

        $salesOrders = app(SalesOrderService::class);
        $so = $salesOrders->create($this->angloTenant->id, $customer->id, $this->location->id, $soCreator);
        $soLine = $salesOrders->addLine($so, $this->product->id, $this->unit->id, '4', '150.0000');
        $salesOrders->sendQuotation($so);
        $salesOrders->confirm($so->fresh(), $soApprover);

        // Teslimat: FIFO COGS (4 * 100.0000 = 400.0000) dr 621 / cr 153.
        $salesOrders->deliver($soLine->fresh(), '4');

        $this->assertSame('400.0000', $this->netDebitBalance($expense->id));
        $this->assertSame(bcsub($stockBeforeDelivery, '400.0000', 4), $this->netDebitBalance($stock->id));

        // Fatura: brüt (4*150=600 + %20 KDV=120 => 720) dr 120 / cr 600(600) / cr 391(120).
        $accountant = $this->user('Accountant');
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create($this->angloTenant->id, $customer->id, 'sale', $so->fresh());
        $invoices->addLine($invoice, $this->product->id, '4', '150.0000', $this->taxRate('sale')->id);
        $invoices->post($invoice, $accountant);

        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertSame('720.0000', $this->netDebitBalance($receivables->id));
        $this->assertSame('-600.0000', $this->netDebitBalance($income->id));
        $this->assertSame('-120.0000', $this->netDebitBalance($outputTax->id));

        // Tahsilat: 720.0000 tam tahsilat, dr 102 / cr 120.
        $payments = app(PaymentService::class);
        $bankJournal = $this->journalOfType('bank');
        $payment = $payments->create($this->angloTenant->id, $customer->id, $bankJournal->id, '720.0000', now()->toDateString());
        $payments->allocate($payment, $invoice, '720.0000');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('720.0000', $this->netDebitBalance($bank->id));

        // Kabul kriteri: 120 (Alıcılar) hesabının net bakiyesi sıfır — alacak tamamen tahsil edildi.
        $this->assertSame('0.0000', $this->netDebitBalance($receivables->id));

        $this->assertTrialBalanceIsBalanced();
    }
}
