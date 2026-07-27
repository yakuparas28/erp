# Faz 10 Ekranları: Kur Tanımları, Döviz Faturası, e-Fatura Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development.

**Goal:** Faz 10'da (Çoklu Para Birimi) yalnızca servis katmanında kalan özellikleri web ekranlarına bağlamak: Kur Tanımları (manuel kur girişi) ekranı, Satınalma/Satış Faturası oluştururken para birimi seçimi + TL karşılığı gösterimi, Ödeme'de para birimi seçimi + kambiyo farkı gösterimi, e-Fatura durum makinesi (Gönder/Kabul Et/Reddet) butonları.

**Architecture:** "Faz 9 Ekranları" turundaki (2026-07-22) BİREBİR aynı konvansiyon — `@extends('app.layouts.app')`, `permission:X,web` route grupları, `_form-modal`/`_status-badge` partial deseni, `computed_*` attribute deseni (N+1'den kaçınmak için model metodlarını view'da döngüde ÇAĞIRMAMA, controller'da eager-loaded ilişkilerden HESAPLAYIP `setAttribute()` ile taşıma — Faz 9 ekranlarının `PaymentController`'ında zaten kurulu). Mevcut `PurchaseInvoiceController`/`SalesInvoiceController`/`PaymentController` DEĞİŞTİRİLİR (yeni alanlar/aksiyonlar EKLENİR, mevcut davranış BOZULMAZ).

## Global Constraints
- İzin eşlemesi (yeni izin İCAT EDİLMEZ): Kur Tanımları ekranı → `manage chart of accounts` (Hesap Planı/Yevmiye ile AYNI grup, muhasebe yapılandırması); Fatura para birimi seçimi + e-Fatura aksiyonları → `post journal entries` (mevcut fatura izniyle AYNI); Ödeme para birimi seçimi → `register payments` (mevcut).
- `InvoiceService::create()`/`PaymentService::create()`'in `?int $currencyId = null` parametresi (Faz 10 backend) DOĞRUDAN kullanılır — servis katmanı DEĞİŞTİRİLMEZ.
- Para birimi seçim listesi HER ZAMAN `Currency::where('is_functional', false)` (yalnızca USD/EUR gibi yabancı birimler) — TRY (`is_functional=true`) hiçbir seçim listesinde GÖRÜNMEZ, boş seçenek "TL (Varsayılan)" anlamına gelir ve `currency_id=null` gönderir.
- `PaymentController::show()`'daki `$openInvoices` sorgusuna `->where('currency_id', $payment->currency_id)` eklenir — Eloquent'in `where()` metodu `null` değeri otomatik `whereNull()`'a çevirir (Laravel'in bilinen davranışı), bu yüzden TL ödemesi (currency_id=null) yalnızca TL faturalarını, USD ödemesi yalnızca USD faturalarını görür (servis katmanının zaten zorunlu kıldığı "aynı para birimi" kuralının GÖRSEL yansıması).
- Her yeni controller aksiyonu bir HTTP feature testiyle kanıtlanır.
- `lang/tr.json`'a her yeni metin eklenir (ZORUNLU, alfabetik sıra).
- `vendor/bin/pint --dirty --format agent` her PHP değişikliğinden sonra.

---

### Task 1: Kur Tanımları (Exchange Rates) ekranı

**Dosyalar:**
- Create: `Modules/Accounting/app/Http/Controllers/ExchangeRateController.php`
- Create: `Modules/Accounting/resources/views/exchange-rates/index.blade.php`, `exchange-rates/_form-modal.blade.php`
- Modify: `Modules/Accounting/routes/web.php`, `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Accounting/ExchangeRateScreensTest.php`

**Interfaces:** `ExchangeRateService::recordManualRate(int $tenantId, int $currencyId, string $date, string $buyRate, string $sellRate): ExchangeRate` (Faz 10 backend Task 2) — DEĞİŞTİRME, doğrudan çağır.

```php
<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;
use Modules\Accounting\Services\ExchangeRateService;

class ExchangeRateController extends Controller
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public function index(): View
    {
        return view('accounting::exchange-rates.index', [
            'rates' => ExchangeRate::with('currency')->orderByDesc('rate_date')->get(),
            'currencies' => Currency::where('is_functional', false)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency_id' => ['required', 'exists:currencies,id'],
            'rate_date' => ['required', 'date'],
            'buy_rate' => ['required', 'numeric', 'gt:0'],
            'sell_rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->exchangeRates->recordManualRate(
            $request->user()->tenant_id,
            (int) $validated['currency_id'],
            (string) $validated['rate_date'],
            (string) $validated['buy_rate'],
            (string) $validated['sell_rate'],
        );

        return redirect()->route('app.accounting.exchange-rates.index')->with('status', __('Exchange rate recorded.'));
    }
}
```

Route (`Modules/Accounting/routes/web.php`'deki MEVCUT `permission:manage chart of accounts,web` grubunun İÇİNE, `journal-entries` satırlarının ARDINA ekle — `use` importunu dosyanın başına ekle):

```php
        Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
        Route::post('/exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');
```

View: `Modules/Accounting/resources/views/accounts/index.blade.php` + `accounts/_form-modal.blade.php` genel iskeletini (başlık+buton, tablo, modal include) BİREBİR REFERANS AL. Tablo kolonları: Para Birimi (`$rate->currency->code`), Tarih, Alış Kuru, Satış Kuru, Kaynak (rozet: `tcmb`/`manual` için farklı renk, `adjustments/index.blade.php`'deki rozet deseni). "Yeni Kur Girişi" modalı: Para Birimi select (`$currencies`), Tarih (`type="date"`), Alış Kuru, Satış Kuru (`type="number" step="0.000001"` — kur kolonu `decimal(15,6)`, diğer para/miktar alanlarından FARKLI hassasiyet).

Menü (`resources/views/app/layouts/app.blade.php`, mevcut Accounting grubuna, "Hesap Planı" öğesinden SONRA):

```blade
                            @can('manage chart of accounts')
                                <li>
                                    <a href="{{ route('app.accounting.exchange-rates.index') }}" class="{{ request()->routeIs('app.accounting.exchange-rates.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-currency-circle-dollar"></i><span>{{ __('Exchange Rates') }}</span>
                                    </a>
                                </li>
                            @endcan
```
(Üstteki `@if` satırı ZATEN `manage chart of accounts`'ı içeriyor — TEKRAR EKLEME.)

- [ ] Controller/route/view/menü ekle
- [ ] `ExchangeRateScreensTest`: liste sayfası (`AccountingDefaultsService::provision()` sonrası seed'lenmiş TRY/USD/EUR'dan yalnızca USD/EUR select'te görünür, TRY GÖRÜNMEZ); manuel kur girme → `exchange_rates` tablosuna `source='manual'` ile kaydedilir; AYNI para birimi+tarih için İKİNCİ kez girme → GÜNCELLENİR (çoğaltılmaz, `assertDatabaseCount` ile doğrula); izinsiz kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=ExchangeRateScreensTest`
- [ ] Commit: `feat(accounting): exchange rate management screen`

### Task 2: Satınalma Faturasında para birimi seçimi + TL karşılığı gösterimi

**Dosyalar:**
- Modify: `Modules/Accounting/app/Http/Controllers/PurchaseInvoiceController.php`, `Modules/Accounting/resources/views/purchase-invoices/index.blade.php`, `show.blade.php`
- Test: `tests/Feature/Accounting/PurchaseInvoiceCurrencyScreensTest.php`

**Interfaces:** `InvoiceService::create(int $tenantId, int $partnerId, string $type, Model $source, ?int $currencyId = null): Invoice` (Faz 10 backend Task 3) — `$currencyId` artık controller'dan geçirilir. `Invoice::exchangeRateOrOne(): string`, `Invoice::currency(): BelongsTo` (Faz 10 backend Task 1).

`PurchaseInvoiceController::index()`'e `$currencies` eklenir, `store()`'a `currency_id` validasyonu eklenir, `show()`'da `currency` eager-load edilir + `computed_total_tl` hesaplanır:

```php
    public function index(): View
    {
        return view('accounting::purchase-invoices.index', [
            'invoices' => Invoice::with(['partner', 'currency', 'lines.taxRate'])->where('type', 'purchase')->latest()->get(),
            'currencies' => Currency::where('is_functional', false)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
        ]);

        $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        $invoice = $this->invoices->create(
            $request->user()->tenant_id,
            $po->partner_id,
            'purchase',
            $po,
            isset($validated['currency_id']) ? (int) $validated['currency_id'] : null,
        );

        return redirect()->route('app.accounting.purchase-invoices.show', $invoice)->with('status', __('Draft invoice created.'));
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['partner', 'currency', 'lines.product', 'lines.taxRate', 'source.lines.product']);
        $invoice->setAttribute('computed_total_tl', bcmul($invoice->total(), $invoice->exchangeRateOrOne(), 4));

        return view('accounting::purchase-invoices.show', [
            'invoice' => $invoice,
            'taxRates' => TaxRate::where('type', 'purchase')->orderBy('percentage')->get(),
        ]);
    }
```
(`use Modules\Accounting\Models\Currency;` importu eklenir. `storeLine()`/`post()` DEĞİŞMEZ.)

View değişiklikleri:
- `index.blade.php`: "Yeni Satınalma Faturası" modalına Para Birimi select'i eklenir (boş seçenek `{{ __('TRY (Default)') }}` değer="", `@foreach ($currencies as $currency) <option value="{{ $currency->id }}">{{ $currency->code }}</option> @endforeach`). Tablo listesinde her fatura satırına Para Birimi kolonu eklenir (`$invoice->currency?->code ?? 'TRY'`).
- `show.blade.php`: fatura başlığında para birimi + kilitli kur gösterilir (`@if ($invoice->currency_id) {{ __('Currency') }}: {{ $invoice->currency->code }} ({{ __('Rate') }}: {{ $invoice->exchange_rate_used }}) @endif`); toplam satırında HEM kendi para biriminde toplam (`$invoice->total()`) HEM (yalnızca `currency_id` doluysa) TL karşılığı (`$invoice->computed_total_tl`) gösterilir.

- [ ] Controller/view değişikliklerini uygula
- [ ] `PurchaseInvoiceCurrencyScreensTest`: USD para birimi seçilerek fatura oluşturma → `exchange_rate_used` kilitlenir (Faz 10 backend'in `ExchangeRateService::lockRateFor()`'ını tetikler — testte önce `recordManualRate()` ile kur girilmeli); show sayfası doğru TL karşılığını gösterir; para birimi seçilmeden (boş) oluşturma → `currency_id=null`, TL karşılığı gösterilmez/kendi tutarıyla aynı; kur tanımlı olmayan bir para birimi seçilirse → hata mesajı (servis katmanının 422'si `back()->withErrors()` ile yakalanır — DİKKAT: `store()` şu an `try/catch` İÇERMİYOR, bu görevde EKLENMELİ çünkü `lockRateFor()` artık 422 fırlatabilir)
- [ ] `php artisan test --compact --filter=Purchase` (regresyon — mevcut PO ekran testleri kırılmamalı)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): purchase invoice currency selection and TL equivalent display`

### Task 3: Satış Faturasında para birimi seçimi + TL karşılığı gösterimi

**Dosyalar:** Task 2 ile birebir simetrik.
- Modify: `Modules/Accounting/app/Http/Controllers/SalesInvoiceController.php`, `Modules/Accounting/resources/views/sales-invoices/index.blade.php`, `show.blade.php`
- Test: `tests/Feature/Accounting/SalesInvoiceCurrencyScreensTest.php`

`SalesInvoiceController` Task 2'nin `PurchaseInvoiceController`'ıyla AYNI YAPIDA (yalnızca `sales_order_id`, `type='sale'`, `TaxRate::where('type','sale')`).

- [ ] Controller/view değişikliklerini uygula (Task 2 ile birebir simetrik)
- [ ] `SalesInvoiceCurrencyScreensTest`: Task 2'deki AYNI senaryolar, satış bağlamında
- [ ] `php artisan test --compact --filter=Sales` (regresyon)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): sales invoice currency selection and TL equivalent display`

### Task 4: Ödemede para birimi seçimi + kambiyo farkı gösterimi

**Dosyalar:**
- Modify: `Modules/Accounting/app/Http/Controllers/PaymentController.php`, `Modules/Accounting/resources/views/payments/index.blade.php`, `show.blade.php`
- Test: `tests/Feature/Accounting/PaymentCurrencyScreensTest.php`

**Interfaces:** `PaymentService::create(..., ?int $currencyId = null): Payment` (Faz 10 backend Task 3). `FxRevaluation` modeli (`invoice_id`, `payment_id`, `type`, `difference_amount`) — Faz 10 backend Task 1/4.

`PaymentController`'a eklenecek/değişecek:

```php
    public function index(): View
    {
        // ... mevcut $payments hesaplaması AYNEN KALIR ...

        return view('accounting::payments.index', [
            'payments' => $payments,
            'partners' => Partner::orderBy('name')->get(),
            'cashAndBankJournals' => Journal::whereIn('type', ['cash', 'bank'])->orderBy('name')->get(),
            'currencies' => Currency::where('is_functional', false)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'exists:partners,id'],
            'journal_id' => ['required', 'exists:journals,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
        ]);

        try {
            $payment = $this->payments->create(
                $request->user()->tenant_id,
                (int) $validated['partner_id'],
                (int) $validated['journal_id'],
                (string) $validated['amount'],
                (string) $validated['payment_date'],
                isset($validated['currency_id']) ? (int) $validated['currency_id'] : null,
            );
        } catch (HttpException $e) {
            return back()->withErrors(['journal_id' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.payments.show', $payment)->with('status', __('Payment recorded.'));
    }

    public function show(Payment $payment): View
    {
        // ... mevcut $payment->load()/computed_unallocated_amount hesaplaması AYNEN KALIR ...

        $openInvoices = Invoice::with(['lines.taxRate', 'allocations'])
            ->where('partner_id', $payment->partner_id)
            ->where('currency_id', $payment->currency_id) // NULL-safe: Eloquent otomatik whereNull()'a çevirir
            ->whereIn('status', ['posted'])
            ->get()
            ->each(function (Invoice $invoice): void { /* mevcut computed_remaining_balance AYNEN KALIR */ })
            ->filter(fn (Invoice $invoice) => bccomp($invoice->computed_remaining_balance, '0', 4) > 0)
            ->values();

        $fxRevaluationsByInvoice = FxRevaluation::where('payment_id', $payment->id)->get()->keyBy('invoice_id');

        return view('accounting::payments.show', [
            'payment' => $payment,
            'openInvoices' => $openInvoices,
            'fxRevaluationsByInvoice' => $fxRevaluationsByInvoice,
        ]);
    }
```
(`use Modules\Accounting\Models\Currency;`, `use Modules\Accounting\Models\FxRevaluation;` importları eklenir. `storeAllocation()`/`sumAllocatedAmounts()` DEĞİŞMEZ.)

View değişiklikleri:
- `index.blade.php`: "Yeni Ödeme" modalına Para Birimi select'i eklenir (Task 1/2'deki AYNI desen).
- `show.blade.php`: mevcut dağıtımlar tablosuna bir "Kambiyo Farkı" kolonu eklenir — her satırda `$fxRevaluationsByInvoice->get($allocation->invoice_id)?->difference_amount` gösterilir (yoksa `—`); AÇIK faturalar select'i artık yalnızca ödemeyle AYNI para birimindeki faturaları listeler (controller değişikliği zaten bunu sağlıyor, view'da ek bir şey gerekmez).

- [ ] Controller/view değişikliklerini uygula
- [ ] `PaymentCurrencyScreensTest`: USD ödeme oluşturma (kur kilitlenir); farklı para biriminde bir ödeme+fatura kombinasyonu AÇIK FATURALAR listesinde GÖRÜNMEZ (yalnızca aynı para birimindeki fatura görünür); gerçekleşen kambiyo farkı olan bir dağıtımdan sonra show sayfası doğru `difference_amount`'ı gösterir; kambiyo farkı OLMAYAN (aynı kur) bir dağıtım için `—` gösterilir
- [ ] `php artisan test --compact --filter=PaymentScreensTest` (regresyon — Faz 9 ekran testleri kırılmamalı)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): payment currency selection and fx gain/loss display`

### Task 5: e-Fatura durum makinesi butonları

**Dosyalar:**
- Modify: `Modules/Accounting/app/Http/Controllers/PurchaseInvoiceController.php`, `SalesInvoiceController.php`, `Modules/Accounting/routes/web.php`, `Modules/Accounting/resources/views/purchase-invoices/show.blade.php`, `sales-invoices/show.blade.php`
- Test: `tests/Feature/Accounting/EInvoiceScreensTest.php`

**Interfaces:** `EInvoiceService::send(Invoice): void`, `markAccepted(Invoice): void`, `markRejected(Invoice): void` (Faz 10 backend Task 6) — DEĞİŞTİRME.

Her iki controller'a (Purchase/Sales, BİREBİR AYNI kod, yalnızca route isimleri `purchase-invoices`/`sales-invoices` farklı) eklenecek:

```php
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly EInvoiceService $eInvoices,
    ) {}

    // ... mevcut metodlar ...

    public function sendEInvoice(Invoice $invoice): RedirectResponse
    {
        try {
            $this->eInvoices->send($invoice);
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.purchase-invoices.show', $invoice)->with('status', __('e-Invoice sent.'));
    }

    public function acceptEInvoice(Invoice $invoice): RedirectResponse
    {
        try {
            $this->eInvoices->markAccepted($invoice);
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.purchase-invoices.show', $invoice)->with('status', __('e-Invoice marked as accepted.'));
    }

    public function rejectEInvoice(Invoice $invoice): RedirectResponse
    {
        try {
            $this->eInvoices->markRejected($invoice);
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.purchase-invoices.show', $invoice)->with('status', __('e-Invoice marked as rejected.'));
    }
```
(`SalesInvoiceController`'da route isimleri `sales-invoices.show` olur, geri kalan BİREBİR AYNI. `use Modules\Accounting\Services\EInvoiceService;` importu eklenir.)

Route (mevcut `permission:post journal entries,web` grubunun İÇİNE, her iki fatura tipi için):

```php
        Route::post('/purchase-invoices/{invoice}/e-invoice/send', [PurchaseInvoiceController::class, 'sendEInvoice'])->name('purchase-invoices.e-invoice.send');
        Route::post('/purchase-invoices/{invoice}/e-invoice/accept', [PurchaseInvoiceController::class, 'acceptEInvoice'])->name('purchase-invoices.e-invoice.accept');
        Route::post('/purchase-invoices/{invoice}/e-invoice/reject', [PurchaseInvoiceController::class, 'rejectEInvoice'])->name('purchase-invoices.e-invoice.reject');

        Route::post('/sales-invoices/{invoice}/e-invoice/send', [SalesInvoiceController::class, 'sendEInvoice'])->name('sales-invoices.e-invoice.send');
        Route::post('/sales-invoices/{invoice}/e-invoice/accept', [SalesInvoiceController::class, 'acceptEInvoice'])->name('sales-invoices.e-invoice.accept');
        Route::post('/sales-invoices/{invoice}/e-invoice/reject', [SalesInvoiceController::class, 'rejectEInvoice'])->name('sales-invoices.e-invoice.reject');
```

View (her iki `show.blade.php`'ye, durum rozetinin yanına e-Fatura durum rozeti + koşullu butonlar eklenir):

```blade
<span class="text-[11px] {{ $invoice->e_invoice_status === 'accepted' ? 'bg-success-transparent text-success' : ($invoice->e_invoice_status === 'rejected' ? 'bg-danger-transparent text-danger' : 'bg-light text-default') }} px-2 py-0.5 rounded">
    {{ __('e-invoice-status.'.$invoice->e_invoice_status) }}
</span>

@if ($invoice->status === 'posted' && $invoice->e_invoice_status === 'not_sent')
    <form method="POST" action="{{ route('app.accounting.purchase-invoices.e-invoice.send', $invoice) }}">
        @csrf
        <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">{{ __('Send e-Invoice') }}</button>
    </form>
@elseif ($invoice->e_invoice_status === 'sent')
    <form method="POST" action="{{ route('app.accounting.purchase-invoices.e-invoice.accept', $invoice) }}">
        @csrf
        <button type="submit" class="btn-sm bg-white border border-success text-success hover:bg-success hover:text-white cursor-pointer">{{ __('Mark Accepted') }}</button>
    </form>
    <form method="POST" action="{{ route('app.accounting.purchase-invoices.e-invoice.reject', $invoice) }}">
        @csrf
        <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer">{{ __('Mark Rejected') }}</button>
    </form>
@endif
```
(`sales-invoices/show.blade.php`'de route isimleri `sales-invoices.e-invoice.*` olur.)

- [ ] Her iki controller'a e-Fatura aksiyonlarını + `EInvoiceService` constructor injection'ı ekle
- [ ] Route'ları ekle
- [ ] Her iki `show.blade.php`'ye durum rozeti + koşullu butonları ekle
- [ ] `EInvoiceScreensTest`: posted bir satınalma faturasında "Gönder" → `e_invoice_status='sent'`; draft bir faturada buton HİÇ GÖRÜNMEZ (`assertDontSee`); sent durumda "Kabul Et"/"Reddet" butonları görünür, tıklanınca doğru durum geçişi olur; AYNI senaryolar satış faturası için de (kod tekrarı test dosyasında OLABİLİR, iki ayrı test metodu grubu — Purchase/Sales — yeterli); izinsiz kullanıcı 403
- [ ] `php artisan test --compact --filter=Accounting` (TAM regresyon)
- [ ] Tüm suite: `php artisan test --compact`
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): e-invoice send/accept/reject actions on invoice screens`

## Self-Review
- Faz 10'un servis katmanındaki HER özellik (kur kilitleme+manuel giriş, çoklu para birimi fatura/ödeme, e-Fatura durum makinesi) ekrana bağlandı — Faz 10 backend'in geride bıraktığı TEK entegrasyon açığı (whole-phase incelemede tespit edilen "hiçbir ekran döviz faturası oluşturamıyor") bu turla kapatıldı.
- Dönem sonu gerçekleşmemiş değerleme (`revaluateOpenBalances()`) BİLİNÇLİ olarak bu turda da EKRANA BAĞLANMADI — Faz 10 backend planının kendi self-review'ünde belirtildiği gibi, reversal mekanizması olmadan üretime açılması riskli (dokümante edilmiş, gelecekteki bir faz için önkoşul).
- Yeni izin İCAT EDİLMEDİ; mevcut üç izin (manage chart of accounts / post journal entries / register payments) bu turun tüm ekranlarına da tutarlı şekilde uygulandı.
- `computed_*` attribute deseni (Faz 9 ekranlarının `PaymentController`'ında kurulan N+1-önleme konvansiyonu) bu turun TÜM yeni hesaplamalarında (`computed_total_tl`, kambiyo farkı gösterimi) tutarlı şekilde kullanıldı.
