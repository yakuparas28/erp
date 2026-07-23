# Faz 9 Ekranları: Hesap Planı, Yevmiye, Fatura, Ödeme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development.

**Goal:** Faz 9'da yalnızca servis katmanında kalan Muhasebe Çekirdeği'ni (`Modules/Accounting`) web ekranlarına bağlamak: Hesap Planı yönetimi, Yevmiye (journal_entries) görüntüleyici (salt okunur — hiçbir kayıt elle girilmez), Satınalma/Satış Faturaları (oluşturma+satır ekleme+onaylama), Ödeme/Tahsilat (oluşturma+dağıtım).

**Architecture:** "Eksik Ekranlar" turundaki (2026-07-21) BİREBİR aynı konvansiyon — `@extends('app.layouts.app')`, `permission:X,web` route grupları, `_status-badge`/`_form-modal` partial deseni, satır-satır ekleme (AdjustmentController/PurchaseOrderController deseni). Tüm ekranlar `Modules/Accounting` altında yaşar (model/servisler zaten orada). `Modules/Accounting`'in kullanılmayan nwidart iskeleti (`AccountingController`, eski `routes/web.php`, `index.blade.php`) TAMAMEN silinip yenisiyle değiştirilir.

## Global Constraints
- İzin eşlemesi (yeni izin İCAT EDİLMEZ, PermissionCatalog'daki `accounting` grubu kullanılır): Hesap Planı + Yevmiye görüntüleme → `manage chart of accounts`; Fatura ekranları (oluşturma/satır/onay) → `post journal entries` (PRD'nin "yalnız Accountant/Tenant Admin" çerçevesiyle tutarlı — bu fazda faturalama tamamen Muhasebe'nin alanı, Satış Temsilcisi/Satınalma Sorumlusu'na açık değil); Ödeme ekranları → `register payments`.
- Servis katmanı davranışı DEĞİŞTİRİLMEZ (`InvoiceService`, `PaymentService`, `JournalEntryService`, `AccountingDefaultsService` — Faz 9'da yazıldı, yalnızca controller'lardan çağrılır).
- `InvoiceService::create()`/`addLine()` PO/SO durumuna göre bir kısıtlama YAPMIYOR (servis katmanında böyle bir kural yok) — ekran da bu kısıtlamayı EKLEMEZ (yeni iş kuralı icat etmek bu turun kapsamı dışında).
- Para/miktar input'ları `type="number" step="0.0001"`; servis katmanına string geçirilir.
- Her yeni controller aksiyonu bir HTTP feature testiyle kanıtlanır.
- `lang/tr.json`'a her yeni metin eklenir (ZORUNLU, önceki turda birkaç kez atlanıp düzeltilmişti — bu kez BAŞTAN eksiksiz).
- `vendor/bin/pint --dirty --format agent` her PHP değişikliğinden sonra.

---

### Task 1: Hesap Planı (Chart of Accounts) ekranı

**Dosyalar:**
- Create: `Modules/Accounting/app/Http/Controllers/ChartOfAccountController.php`
- Create: `Modules/Accounting/resources/views/accounts/index.blade.php`, `accounts/_form-modal.blade.php`
- Modify: `Modules/Accounting/routes/web.php` (TAMAMEN değiştir — eski `AccountingController`/`Route::resource('accountings',...)` SİL), `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Accounting/ChartOfAccountScreensTest.php`

```php
<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;

class ChartOfAccountController extends Controller
{
    public function index(): View
    {
        return view('accounting::accounts.index', [
            'accounts' => ChartOfAccount::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,income,expense'],
        ]);

        ChartOfAccount::create($validated);

        return redirect()->route('app.accounting.accounts.index')->with('status', __('Account added.'));
    }

    public function destroy(ChartOfAccount $account): RedirectResponse
    {
        $isUsed = JournalEntryLine::where('account_id', $account->id)->exists();

        if ($isUsed) {
            return back()->withErrors(['account' => __('This account has journal entries and cannot be deleted.')]);
        }

        $account->delete();

        return redirect()->route('app.accounting.accounts.index')->with('status', __('Account deleted.'));
    }
}
```

Route (`Modules/Accounting/routes/web.php` — TAMAMEN yeniden yaz, bu dosya Task 2-5'te de genişleyecek):

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;

Route::middleware(['auth:web'])->prefix('app/accounting')->name('app.accounting.')->group(function (): void {
    Route::middleware('permission:manage chart of accounts,web')->group(function (): void {
        Route::get('/accounts', [ChartOfAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [ChartOfAccountController::class, 'store'])->name('accounts.store');
        Route::delete('/accounts/{account}', [ChartOfAccountController::class, 'destroy'])->name('accounts.destroy');
    });
});
```

View: `Modules/Inventory/resources/views/products/index.blade.php` + `products/_form-modal.blade.php` genel iskeletini (başlık+buton, tablo, modal include) REFERANS AL. Tablo kolonları: Kod, Ad, Tip (rozet — asset/liability/equity/income/expense için farklı renkler, `adjustments/index.blade.php`'deki rozet deseni), Sil butonu (`onsubmit="return confirm('...')"`, `templates/index.blade.php` deseni). `_form-modal`: code/name/type (select) alanları.

Menü (`resources/views/app/layouts/app.blade.php`, Sales grubundan SONRA, Administration'dan ÖNCE, YENİ bir "Accounting" grubu — bu grup Task 2-5'te de genişleyecek):

```blade
                        @if (auth()->user()?->can('manage chart of accounts') || auth()->user()?->can('post journal entries') || auth()->user()?->can('register payments'))
                            <li class="menu-title" aria-disabled="true"><span>{{ __('Accounting') }}</span></li>
                            @can('manage chart of accounts')
                                <li>
                                    <a href="{{ route('app.accounting.accounts.index') }}" class="{{ request()->routeIs('app.accounting.accounts.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-book-open-text"></i><span>{{ __('Chart of Accounts') }}</span>
                                    </a>
                                </li>
                            @endcan
                        @endif
```

- [ ] Eski `AccountingController`/`routes/web.php`/`index.blade.php` sil, yenilerini ekle
- [ ] Menü grubu ekle (Task 2-5 aynı `@if` bloğuna yeni `@can` satırları ekleyecek, TEKRAR `@if` açmaya gerek yok)
- [ ] `ChartOfAccountScreensTest`: liste sayfası hesapları gösterir; yeni hesap ekleme; kullanımda OLMAYAN hesabı silme; `journal_entry_lines`'da kullanılan hesabı silmeye çalışma → hata mesajı + hesap SİLİNMEZ; izinsiz kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=ChartOfAccountScreensTest`
- [ ] Commit: `feat(accounting): chart of accounts management screen`

### Task 2: Yevmiye (Journal Entries) görüntüleyici — salt okunur

**Dosyalar:**
- Create: `Modules/Accounting/app/Http/Controllers/JournalEntryViewerController.php`
- Create: `Modules/Accounting/resources/views/journal-entries/index.blade.php`, `journal-entries/show.blade.php`
- Modify: `Modules/Accounting/routes/web.php`, `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Accounting/JournalEntryViewerScreensTest.php`

**Not:** Bu ekranda HİÇBİR yazma aksiyonu (create/edit/delete) YOKTUR — PRD: "hiçbir muhasebe kaydı elle girilmez." Yalnızca `index()`/`show()`.

```php
<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Accounting\Models\JournalEntry;

class JournalEntryViewerController extends Controller
{
    public function index(): View
    {
        return view('accounting::journal-entries.index', [
            'entries' => JournalEntry::with(['journal', 'lines'])->latest('entry_date')->latest('id')->get(),
        ]);
    }

    public function show(JournalEntry $entry): View
    {
        return view('accounting::journal-entries.show', [
            'entry' => $entry->load(['journal', 'lines.account']),
        ]);
    }
}
```

Route ekle (aynı `permission:manage chart of accounts,web` grubuna):

```php
        Route::get('/journal-entries', [JournalEntryViewerController::class, 'index'])->name('journal-entries.index');
        Route::get('/journal-entries/{entry}', [JournalEntryViewerController::class, 'show'])->name('journal-entries.show');
```

`journal-entries/index.blade.php`: tablo — Tarih, Defter (journal.name), Referans Tipi (`$entry->reference_type` — morph alias, ör. `purchase_order_line`/`invoice`/`payment`), Toplam Tutar (satırların debit toplamı), Durum, "Görüntüle" linki.
`journal-entries/show.blade.php`: başlıkta tarih+defter, satır tablosu (Hesap Kodu, Hesap Adı, Borç, Alacak), altta toplam satırı (borç toplamı = alacak toplamı olduğunu gösteren bir özet — bu, denge kuralının GÖRSEL kanıtı).

Menü (Task 1'deki Accounting grubuna EKLE, `manage chart of accounts` `@can` bloğundan sonra):

```blade
                            @can('manage chart of accounts')
                                <li>
                                    <a href="{{ route('app.accounting.journal-entries.index') }}" class="{{ request()->routeIs('app.accounting.journal-entries.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-notebook"></i><span>{{ __('Journal Entries') }}</span>
                                    </a>
                                </li>
                            @endcan
```

- [ ] Controller/route/view/menü ekle
- [ ] `JournalEntryViewerScreensTest`: liste sayfası gerçek journal_entries'i gösterir (Task 9'daki `AccountingEndToEndTest` deseninde bir PO receive() tetikleyip oluşan kaydı doğrula); detay sayfası satırları (hesap kodu/borç/alacak) doğru gösterir; izinsiz kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=JournalEntryViewerScreensTest`
- [ ] Commit: `feat(accounting): read-only journal entry viewer`

### Task 3: Satınalma Faturaları (Vendor Bills) ekranı

**Dosyalar:**
- Create: `Modules/Accounting/app/Http/Controllers/PurchaseInvoiceController.php`
- Create: `Modules/Accounting/resources/views/purchase-invoices/index.blade.php`, `show.blade.php`
- Modify: `Modules/Accounting/routes/web.php`, `resources/views/app/layouts/app.blade.php`, `Modules/Purchase/resources/views/orders/show.blade.php` ("Fatura Oluştur" butonu eklenir)
- Test: `tests/Feature/Accounting/PurchaseInvoiceScreensTest.php`

**Interfaces:** `InvoiceService::create(int $tenantId, int $partnerId, string $type, Model $source): Invoice`, `addLine(Invoice, int $productId, string $qty, string $unitPrice, ?int $taxRateId): InvoiceLine`, `post(Invoice, User $poster): void` (Faz 9 Task 6-7).

```php
<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\TaxRate;
use Modules\Accounting\Services\InvoiceService;
use Modules\Purchase\Models\PurchaseOrder;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchaseInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(): View
    {
        return view('accounting::purchase-invoices.index', [
            'invoices' => Invoice::with(['partner', 'lines'])->where('type', 'purchase')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['purchase_order_id' => ['required', 'exists:purchase_orders,id']]);

        $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        $invoice = $this->invoices->create($request->user()->tenant_id, $po->partner_id, 'purchase', $po);

        return redirect()->route('app.accounting.purchase-invoices.show', $invoice)->with('status', __('Draft invoice created.'));
    }

    public function show(Invoice $invoice): View
    {
        return view('accounting::purchase-invoices.show', [
            'invoice' => $invoice->load(['partner', 'lines.product', 'lines.taxRate', 'source.lines.product']),
            'taxRates' => TaxRate::where('type', 'purchase')->orderBy('percentage')->get(),
        ]);
    }

    public function storeLine(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
        ]);

        try {
            $this->invoices->addLine(
                $invoice,
                (int) $validated['product_id'],
                (string) $validated['qty'],
                (string) $validated['unit_price'],
                isset($validated['tax_rate_id']) ? (int) $validated['tax_rate_id'] : null,
            );
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.purchase-invoices.show', $invoice)->with('status', __('Line added.'));
    }

    public function post(Request $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoices->post($invoice, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.purchase-invoices.show', $invoice)->with('status', __('Invoice posted.'));
    }
}
```

Route ekle (YENİ bir `permission:post journal entries,web` grubu, Task 1'in `manage chart of accounts` grubunun dışında):

```php
    Route::middleware('permission:post journal entries,web')->group(function (): void {
        Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index'])->name('purchase-invoices.index');
        Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store'])->name('purchase-invoices.store');
        Route::get('/purchase-invoices/{invoice}', [PurchaseInvoiceController::class, 'show'])->name('purchase-invoices.show');
        Route::post('/purchase-invoices/{invoice}/lines', [PurchaseInvoiceController::class, 'storeLine'])->name('purchase-invoices.lines.store');
        Route::post('/purchase-invoices/{invoice}/post', [PurchaseInvoiceController::class, 'post'])->name('purchase-invoices.post');
    });
```

`purchase-invoices/index.blade.php`: `Modules/Purchase/resources/views/orders/index.blade.php` iskeletini REFERANS AL. Kolonlar: Tedarikçi, Durum rozeti (draft/posted/paid/cancelled), Toplam (`$invoice->total()`), "Görüntüle" linki.

`purchase-invoices/show.blade.php`: `Modules/Purchase/resources/views/orders/show.blade.php` deseninde. Üstte PO referansı (`$invoice->source->id` linki `app.purchase.orders.show`'a). `status='draft'` iken: satır ekleme formu — ürün select'i `$invoice->source->lines` (PO satırları) üzerinden doldurulur (`@foreach ($invoice->source->lines as $poLine)` → `<option value="{{ $poLine->product_id }}">{{ $poLine->product->name }} ({{ __('ordered') }}: {{ $poLine->qty }}, {{ __('received') }}: {{ $poLine->receivedQty() }})</option>`), qty/unit_price input (PO satırının değerleriyle JS'siz varsayılan doldurma GEREKMEZ, kullanıcı elle girer), KDV oranı select (`$taxRates`), "Onayla" (post) butonu draft durumda satır varsa görünür. `status != 'draft'` iken salt okunur satır tablosu (Ürün, Miktar, Birim Fiyat, KDV Oranı, Ara Toplam, KDV Tutarı) + toplam satırı (`$invoice->total()`).

`Modules/Purchase/resources/views/orders/show.blade.php`'ye eklenecek (PO durumu `confirmed` veya `done` iken görünür bir buton):

```blade
@if (in_array($po->status, ['confirmed', 'done']))
    <form method="POST" action="{{ route('app.accounting.purchase-invoices.store') }}">
        @csrf
        <input type="hidden" name="purchase_order_id" value="{{ $po->id }}">
        <button type="submit" class="btn-sm bg-white border border-border-color text-gray-900 inline-flex items-center gap-2 hover:bg-light cursor-pointer">
            <i class="ph ph-receipt"></i> {{ __('Create Invoice') }}
        </button>
    </form>
@endif
```
(Bu formun PO show sayfasındaki mevcut aksiyon butonlarının yanına, `@can('post journal entries')` ile sarmalanmış olarak eklenmesi gerekir — kullanıcı bu izne sahip değilse buton hiç görünmemeli.)

Menü (Accounting grubuna EKLE):

```blade
                            @can('post journal entries')
                                <li>
                                    <a href="{{ route('app.accounting.purchase-invoices.index') }}" class="{{ request()->routeIs('app.accounting.purchase-invoices.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-file-text"></i><span>{{ __('Purchase Invoices') }}</span>
                                    </a>
                                </li>
                            @endcan
```
(Task 1'deki üstteki `@if` satırına `post journal entries` zaten eklenmişti — TEKRAR ekleme.)

- [ ] Controller/route/view/menü + PO show sayfasına buton ekle
- [ ] `PurchaseInvoiceScreensTest`: PO'dan taslak fatura oluşturma; satır ekleme (PO satırından ürün seçimi); 3 yönlü eşleştirme aşımında hata mesajı gösterilir (satır eklenmez); onaylama (post) → `journal_entries` oluşur, fatura `status='posted'`; `post journal entries` izni olmayan kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=PurchaseInvoiceScreensTest`
- [ ] Commit: `feat(accounting): purchase invoice screens (creation from PO, lines, posting)`

### Task 4: Satış Faturaları ekranı

**Dosyalar:** Task 3 ile birebir simetrik.
- Create: `Modules/Accounting/app/Http/Controllers/SalesInvoiceController.php`
- Create: `Modules/Accounting/resources/views/sales-invoices/index.blade.php`, `show.blade.php`
- Modify: `Modules/Accounting/routes/web.php`, `resources/views/app/layouts/app.blade.php`, `Modules/Sales/resources/views/orders/show.blade.php`
- Test: `tests/Feature/Accounting/SalesInvoiceScreensTest.php`

`SalesInvoiceController` Task 3'ün `PurchaseInvoiceController`'ıyla AYNI YAPIDA — farklar: `store()` `sales_order_id` alır, `SalesOrder::findOrFail()`, `type='sale'`; `TaxRate::where('type','sale')`; route isimleri `app.accounting.sales-invoices.*`.

`Modules/Sales/resources/views/orders/show.blade.php`'ye Task 3'teki AYNI butonu ekle (route: `app.accounting.sales-invoices.store`, body: `sales_order_id`, SO durumu `confirmed`/`done` iken görünür).

Menü (Accounting grubuna EKLE, Purchase Invoices'tan sonra):

```blade
                            @can('post journal entries')
                                <li>
                                    <a href="{{ route('app.accounting.sales-invoices.index') }}" class="{{ request()->routeIs('app.accounting.sales-invoices.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-receipt"></i><span>{{ __('Sales Invoices') }}</span>
                                    </a>
                                </li>
                            @endcan
```

- [ ] Controller/route/view/menü + SO show sayfasına buton ekle
- [ ] `SalesInvoiceScreensTest`: SO'dan taslak fatura oluşturma; satır ekleme (miktar sınırlaması YOK — PO'daki gibi 3 yönlü eşleştirme satışta uygulanmaz, herhangi bir miktar kabul edilir); onaylama (post) → dr Alıcılar/cr gelir+KDV, fatura `status='posted'`; izinsiz kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=SalesInvoiceScreensTest`
- [ ] Commit: `feat(accounting): sales invoice screens (creation from SO, lines, posting)`

### Task 5: Ödeme/Tahsilat ekranı

**Dosyalar:**
- Create: `Modules/Accounting/app/Http/Controllers/PaymentController.php`
- Create: `Modules/Accounting/resources/views/payments/index.blade.php`, `show.blade.php`
- Modify: `Modules/Accounting/routes/web.php`, `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Accounting/PaymentScreensTest.php`

**Interfaces:** `PaymentService::create(int $tenantId, int $partnerId, int $journalId, string $amount, string $paymentDate): Payment`, `allocate(Payment, Invoice, string $amount): PaymentAllocation` (Faz 9 Task 8).

```php
<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Accounting\Services\PaymentService;
use Modules\Inventory\Models\Partner;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(): View
    {
        return view('accounting::payments.index', [
            'payments' => Payment::with(['partner', 'journal'])->latest()->get(),
            'partners' => Partner::orderBy('name')->get(),
            'cashAndBankJournals' => Journal::whereIn('type', ['cash', 'bank'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'exists:partners,id'],
            'journal_id' => ['required', 'exists:journals,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
        ]);

        try {
            $payment = $this->payments->create(
                $request->user()->tenant_id,
                (int) $validated['partner_id'],
                (int) $validated['journal_id'],
                (string) $validated['amount'],
                (string) $validated['payment_date'],
            );
        } catch (HttpException $e) {
            return back()->withErrors(['journal_id' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.payments.show', $payment)->with('status', __('Payment recorded.'));
    }

    public function show(Payment $payment): View
    {
        return view('accounting::payments.show', [
            'payment' => $payment->load(['partner', 'journal', 'allocations.invoice']),
            'openInvoices' => Invoice::where('partner_id', $payment->partner_id)
                ->whereIn('status', ['posted'])
                ->get()
                ->filter(fn (Invoice $invoice) => bccomp($invoice->remainingBalance(), '0', 4) > 0)
                ->values(),
        ]);
    }

    public function storeAllocation(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        try {
            $this->payments->allocate($payment, $invoice, (string) $validated['amount']);
        } catch (HttpException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('app.accounting.payments.show', $payment)->with('status', __('Allocated.'));
    }
}
```

Route ekle (`permission:register payments,web` grubu):

```php
    Route::middleware('permission:register payments,web')->group(function (): void {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/allocations', [PaymentController::class, 'storeAllocation'])->name('payments.allocations.store');
    });
```

`payments/index.blade.php`: liste (Partner, Defter, Tutar, Tarih, Dağıtılmamış Tutar (`$payment->unallocatedAmount()`), "Görüntüle") + "Yeni Ödeme/Tahsilat" modalı (partner select, journal select [yalnızca cash/bank], tutar, tarih).

`payments/show.blade.php`: ödeme özeti (partner, tutar, dağıtılmamış tutar) + mevcut dağıtımlar tablosu (Fatura, Dağıtılan Tutar) + AÇIK bir fatura varsa (`$openInvoices` boş değilse) dağıtım formu (fatura select — `{{ $invoice->partner... }} #{{ $invoice->id }} — {{ __('remaining') }}: {{ $invoice->remainingBalance() }}`, tutar input).

Menü (Accounting grubuna EKLE, Sales Invoices'tan sonra):

```blade
                            @can('register payments')
                                <li>
                                    <a href="{{ route('app.accounting.payments.index') }}" class="{{ request()->routeIs('app.accounting.payments.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-wallet"></i><span>{{ __('Payments') }}</span>
                                    </a>
                                </li>
                            @endcan
```
(Task 1'deki üstteki `@if` satırına `register payments` zaten eklenmişti — TEKRAR ekleme.)

- [ ] Controller/route/view/menü ekle
- [ ] `PaymentScreensTest`: ödeme oluşturma (cash/bank journal); `sale`/`purchase` DIŞINDA bir journal ile oluşturma denemesi → hata; tek ödemenin bir faturaya TAM dağıtımı → `journal_entries` oluşur, fatura `paid`; KISMİ dağıtım → fatura `posted` kalır, `unallocatedAmount()` doğru; dağıtım tutarı aşımında hata mesajı; izinsiz kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=PaymentScreensTest`
- [ ] Tüm suite: `php artisan test --compact`
- [ ] Commit: `feat(accounting): payment and allocation screens`

## Self-Review
- Faz 9'un servis katmanındaki HER özellik (Hesap Planı, Yevmiye, Fatura oluşturma/3-yönlü-eşleştirme/onay, Ödeme/kısmi-dağıtım) ekrana bağlandı.
- Yevmiye ekranı BİLİNÇLİ olarak salt okunur — PRD'nin "hiçbir muhasebe kaydı elle girilmez" ilkesiyle tutarlı.
- Yeni izin İCAT EDİLMEDİ; mevcut `accounting` grubu (manage chart of accounts / post journal entries / register payments) üç ekran kümesine ayrıştırıldı, PRD'nin rol çerçevesiyle (yalnızca Accountant/Tenant Admin) tutarlı.
- `Modules/Accounting`'in kullanılmayan nwidart iskeleti SİLİNİYOR (Purchase/Sales ekran turunda olduğu gibi), yarım kalan kod bırakılmıyor.
