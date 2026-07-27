<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\ExchangeRateService;
use Modules\Accounting\Services\InvoiceService;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Sales\Models\SalesOrder;
use Tests\TenantTestCase;

/**
 * Faz 10 uçtan uca kabul kriteri: "USD $100 satış faturası kur 30'da
 * kilitlenir, kur 32'de tam tahsil edilir → 646 (Kambiyo Karları)'ya TAM
 * 200 TL alacak yazılır." Task 4'ün RealizedFxGainLossTest'inden farkı:
 * bu test kuru recordManualRate() ile kısayoldan girmez; gerçek bir TCMB
 * XML yanıtını Http::fake() ile taklit edip ExchangeRateService::syncFromTcmb()
 * üzerinden iki farklı gün için senkronize eder — PRD 3.14'ün "günlük TCMB
 * senkronizasyonu" akışının tamamını, tam bir AccountingDefaultsService::provision()
 * tenant kurulumu üzerinde, tek bir zincirde kanıtlar.
 */
class MultiCurrencyEndToEndTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingDefaultsService::class)->provision($this->tenant);
    }

    private function fakeTcmbXml(string $date, string $usdBuy, string $usdSell): string
    {
        return <<<XML
        <?xml version="1.0" encoding="ISO-8859-9"?>
        <Tarih_Date Tarih="{$date}" Date="{$date}" Bulten_No="2026/1">
        <Currency CrossOrder="0" Kod="USD" CurrencyCode="USD">
        <Unit>1</Unit>
        <Isim>ABD DOLARI</Isim>
        <CurrencyName>US DOLLAR</CurrencyName>
        <ForexBuying>{$usdBuy}</ForexBuying>
        <ForexSelling>{$usdSell}</ForexSelling>
        <BanknoteBuying>{$usdBuy}</BanknoteBuying>
        <BanknoteSelling>{$usdSell}</BanknoteSelling>
        </Currency>
        </Tarih_Date>
        XML;
    }

    private function invoices(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function accountant(): User
    {
        setPermissionsTeamId($this->tenant->id);
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('Accountant');

        return $user;
    }

    private function journalOfType(string $type): Journal
    {
        return Journal::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('type', $type)->firstOrFail();
    }

    private function currency(string $code): Currency
    {
        return Currency::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    private function productWithIncomeCategory(): Product
    {
        $defaults = app(AccountingDefaultsService::class);

        $category = ProductCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'income_account_id' => $defaults->accountByCode($this->tenant->id, '600')->id,
        ]);

        return Product::factory()->create(['tenant_id' => $this->tenant->id, 'product_category_id' => $category->id]);
    }

    private function assertTrialBalanceIsBalanced(): void
    {
        $totalDebit = JournalEntryLine::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sum('debit');
        $totalCredit = JournalEntryLine::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sum('credit');

        $this->assertSame(bcadd((string) $totalDebit, '0', 4), bcadd((string) $totalCredit, '0', 4));
    }

    public function test_usd_invoice_with_rate_30_and_payment_with_rate_32_produces_a_200_try_realized_gain(): void
    {
        $invoiceDate = now()->toDateString();
        $paymentDate = now()->addDay()->toDateString();

        Http::fake([
            'tcmb.gov.tr/*' => Http::sequence()
                ->push($this->fakeTcmbXml(now()->format('d.m.Y'), '30.000000', '30.100000'))
                ->push($this->fakeTcmbXml(now()->addDay()->format('d.m.Y'), '32.000000', '32.100000')),
        ]);

        $exchangeRates = app(ExchangeRateService::class);
        $exchangeRates->syncFromTcmb($this->tenant->id, $invoiceDate);
        $exchangeRates->syncFromTcmb($this->tenant->id, $paymentDate);

        $usd = $this->currency('USD');

        $partner = Partner::factory()->create(['tenant_id' => $this->tenant->id]);
        $salesOrder = SalesOrder::factory()->create(['tenant_id' => $this->tenant->id, 'partner_id' => $partner->id]);
        $product = $this->productWithIncomeCategory();

        $invoice = $this->invoices()->create($this->tenant->id, $partner->id, 'sale', $salesOrder, $usd->id);
        $this->assertSame('30.000000', $invoice->exchange_rate_used);

        $this->invoices()->addLine($invoice, $product->id, '1', '100.0000', null);
        $this->invoices()->post($invoice, $this->accountant());

        $cashJournal = $this->journalOfType('cash');
        $payment = $this->payments()->create(
            $this->tenant->id, $partner->id, $cashJournal->id, '100.0000', $paymentDate, $usd->id
        );
        $this->assertSame('32.000000', $payment->exchange_rate_used);

        $this->payments()->allocate($payment, $invoice, '100.0000');

        $gainAccount = app(AccountingDefaultsService::class)->accountByCode($this->tenant->id, '646');

        // Kabul kriteri: (32 - 30) * 100 = 200 TL kambiyo karı, 646'ya TAM alacak.
        $totalGainCredit = JournalEntryLine::withoutGlobalScopes()
            ->where('account_id', $gainAccount->id)->sum('credit');
        $this->assertSame('200.0000', bcadd((string) $totalGainCredit, '0', 4));

        $this->assertTrialBalanceIsBalanced();
    }
}
