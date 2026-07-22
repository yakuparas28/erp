# Eksik Ekranlar: Partner, Satınalma, Satış, Rota/Putaway/Reordering Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faz 9'a geçmeden önce, Faz 6-8'de yalnızca servis/model katmanında kalan özellikler için eksiksiz web ekranları oluşturmak: Partner (müşteri/tedarikçi) yönetimi, Satınalma sipariş akışı, Satış sipariş akışı, Putaway kuralları, Yeniden Sipariş kuralları + öneriler, Rotalar.

**Architecture:** Bu iş yeni bir mimari kavram getirmiyor — mevcut, kanıtlanmış Blade/controller konvansiyonunu (ProductController/ProductTemplateController/AdjustmentController deseninde: `@extends('app.layouts.app')`, inline modal formlar `_form-modal.blade.php` partial'ı, satır-satır ekleme deseni `AdjustmentController::addCount` gibi, durum geçişi butonları `adjustments/show.blade.php`'deki gibi) birebir tekrar ediyor. `Modules/Sales` ve `Modules/Purchase`'ın mevcut route/controller/view'ları nwidart'ın kullanılmamış iskelet placeholder'ları (`Route::resource(...)`, `<h1>Hello World</h1>`) — bu dosyalar TAMAMEN silinip gerçek içerikle değiştiriliyor. Partner ekranı `Modules/Inventory` altında (model zaten orada, paylaşımlı çekirdek kavram — Faz 7 mimari kararı).

## Global Constraints
- Tüm route grupları `Route::middleware(['auth:web'])->prefix('app/<module>')->name('app.<module>.')->group(...)` desenini kullanır; her alt grup ilgili `permission:X,web` middleware'i ile sarılır (bkz. `Modules/Inventory/routes/web.php`).
- Görev ayrılığı kontrolü HEM serviste (zaten var, 403 atar) HEM view'da tekrarlanır: controller `canConfirm`/`canApprove` gibi bir boolean hesaplayıp view'a geçirir (`AdjustmentController::show()`'daki `$canApprove` deseni), buton yoksa/disable ise kullanıcı deneyip 403 almaz, açıklayıcı bir mesaj görür.
- Menüye yeni öğe eklerken `resources/views/app/layouts/app.blade.php`'deki mevcut `@if(...)/@can(...)` desenine BİREBİR uyulur — yeni bir menü grubu için üstteki `@if (auth()->user()?->can(...))` satırına yeni izin de eklenir.
- Para/miktar input'ları HTML'de `type="number" step="0.0001"` (bkz. adjustments/show.blade.php satır 33); sunucu tarafında değerler string olarak servis katmanına geçirilir (`(string) $validated['qty']`), asla float cast edilmez.
- Modules/Inventory KESİNLİKLE `Modules\Purchase\*` veya `Modules\Sales\*` namespace'inden hiçbir sınıf import ETMEZ (module.json `requires` yönü: Sales/Purchase → Inventory, tersi değil). Bu yüzden Partner ekranında silme (destroy) YOK — yalnızca index/store/update (Warehouses ekranıyla aynı kapsam, referential-integrity kontrolü çapraz modül bağımlılığı gerektirirdi).
- Her yeni controller aksiyonu bir HTTP feature testiyle kanıtlanır (`tests/Feature/.../*ScreensTest.php`, `TenantTestCase` uzantısı, `$this->actingAs($this->tenantAdmin)->get(...)/post(...)` deseni — bkz. `tests/Feature/Inventory/InventoryScreensTest.php`).
- `vendor/bin/pint --dirty --format agent` her PHP değişikliğinden sonra.
- Yeni metinler `__('EN')` ile sarmalanır.

---

### Task 1: Partner (Müşteri/Tedarikçi) ekranı (Inventory)

**Dosyalar:**
- Create: `Modules/Inventory/app/Http/Controllers/PartnerController.php`
- Create: `Modules/Inventory/resources/views/partners/index.blade.php`, `Modules/Inventory/resources/views/partners/_form-modal.blade.php`
- Modify: `Modules/Inventory/routes/web.php`, `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Inventory/PartnerScreensTest.php`

`PartnerController` (`Modules/Inventory/app/Models/Partner.php` zaten var — fillable: `name,tax_number,is_customer,is_supplier,payment_term_days`):

```php
<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Partner;

class PartnerController extends Controller
{
    public function index(): View
    {
        return view('inventory::partners.index', [
            'partners' => Partner::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $partner = Partner::create($validated);

        return redirect()->route('app.inventory.partners.index')->with('status', __(':name added.', ['name' => $partner->name]));
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $validated = $this->validated($request);

        $partner->update($validated);

        return redirect()->route('app.inventory.partners.index')->with('status', __(':name updated.', ['name' => $partner->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'is_customer' => ['nullable', 'boolean'],
            'is_supplier' => ['nullable', 'boolean'],
            'payment_term_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['is_customer'] = $request->boolean('is_customer');
        $validated['is_supplier'] = $request->boolean('is_supplier');
        $validated['payment_term_days'] = $validated['payment_term_days'] ?? 0;

        return $validated;
    }
}
```

Route (`Modules/Inventory/routes/web.php`'ye, mevcut `Route::middleware(['auth:web'])->prefix('app/inventory')->name('app.inventory.')->group(...)` içine yeni bir alt grup ekle):

```php
    Route::middleware('permission:manage partners,web')->group(function (): void {
        Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
        Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
    });
```
(`use Modules\Inventory\Http\Controllers\PartnerController;` importunu dosyanın başına ekle.)

View (`partners/index.blade.php`) — `Modules/Inventory/resources/views/products/index.blade.php`'nin genel iskeletini (başlık+buton satırı, tablo, `_form-modal` include deseni) REFERANS AL ve aynı CSS class'larını kullan. Tablo kolonları: Ad, Vergi No, Müşteri mi (evet/hayır rozeti), Tedarikçi mi (evet/hayır rozeti), Vade (gün), Düzenle butonu (`data-hs-overlay="#edit-partner-modal-{{ $partner->id }}"`). Üstte "Yeni Partner" butonu `data-hs-overlay="#add-partner-modal"`. Sayfa sonunda `@include('inventory::partners._form-modal', ['id' => 'add-partner-modal', 'action' => route('app.inventory.partners.store'), 'method' => 'POST', 'title' => __('New Partner'), 'partner' => null])` ve her partner için edit modal include'u (`Modules/Inventory/resources/views/products/_form-modal.blade.php`'yi AÇIP oku, aynı `@csrf`/`@method` + checkbox deseniyle `_form-modal.blade.php`'yi yaz — alanlar: name, tax_number, is_customer checkbox, is_supplier checkbox, payment_term_days number input).

Menü (`resources/views/app/layouts/app.blade.php`, Inventory `@if` bloğunun içine, Transfers'ten sonra):

```blade
                            @can('manage partners')
                                <li>
                                    <a href="{{ route('app.inventory.partners.index') }}" class="{{ request()->routeIs('app.inventory.partners.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-handshake"></i><span>{{ __('Partners') }}</span>
                                    </a>
                                </li>
                            @endcan
```
Ve üstteki `@if (auth()->user()?->can('view stock') || ...)` satırına `|| auth()->user()?->can('manage partners')` ekle.

- [ ] Controller/route/view/menü ekle
- [ ] `PartnerScreensTest`: liste sayfası partnerleri gösterir; yeni partner oluşturma (is_customer=true) `partners` tablosuna doğru kaydediyor; güncelleme çalışıyor; `manage partners` izni olmayan kullanıcı 403 alıyor
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=PartnerScreensTest`
- [ ] Commit: `feat(inventory): partner management screen`

### Task 2: Satınalma Sipariş ekranı (Modules/Purchase)

**Dosyalar:**
- Delete/replace: `Modules/Purchase/app/Http/Controllers/PurchaseController.php`, `Modules/Purchase/routes/web.php`, `Modules/Purchase/resources/views/index.blade.php` (nwidart placeholder — sil)
- Create: `Modules/Purchase/app/Http/Controllers/PurchaseOrderController.php`
- Create: `Modules/Purchase/resources/views/orders/index.blade.php`, `orders/show.blade.php`
- Modify: `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Purchase/PurchaseOrderScreensTest.php`

`PurchaseOrderController`:

```php
<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Inventory\Models\Product;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;
use Modules\Purchase\Services\PurchaseOrderService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrders) {}

    public function index(): View
    {
        return view('purchase::orders.index', [
            'orders' => PurchaseOrder::with(['partner', 'lines'])->latest()->get(),
            'suppliers' => Partner::where('is_supplier', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['partner_id' => ['required', 'exists:partners,id']]);

        $po = $this->purchaseOrders->create($request->user()->tenant_id, (int) $validated['partner_id'], $request->user());

        return redirect()->route('app.purchase.orders.show', $po)->with('status', __('Purchase order created.'));
    }

    public function show(PurchaseOrder $po): View
    {
        return view('purchase::orders.show', [
            'po' => $po->load(['partner', 'lines.product', 'lines.uom', 'creator']),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
            'canConfirm' => $po->created_by !== auth()->id() && auth()->user()->can('confirm purchase orders'),
        ]);
    }

    public function storeLine(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'uom_id' => ['required', 'exists:uoms,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->purchaseOrders->addLine(
                $po,
                (int) $validated['product_id'],
                (int) $validated['uom_id'],
                (string) $validated['qty'],
                (string) $validated['unit_price'],
            );
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $po)->with('status', __('Line added.'));
    }

    public function sendRfq(PurchaseOrder $po): RedirectResponse
    {
        try {
            $this->purchaseOrders->sendRfq($po);
        } catch (HttpException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $po)->with('status', __('Sent as RFQ.'));
    }

    public function confirm(Request $request, PurchaseOrder $po): RedirectResponse
    {
        try {
            $this->purchaseOrders->confirm($po, $request->user());
        } catch (HttpException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $po)->with('status', __('Purchase order confirmed.'));
    }

    public function cancel(PurchaseOrder $po): RedirectResponse
    {
        try {
            $this->purchaseOrders->cancel($po);
        } catch (HttpException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.index')->with('status', __('Purchase order cancelled.'));
    }

    public function receive(Request $request, PurchaseOrderLine $line): RedirectResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'numeric', 'gt:0'],
            'receiving_location_id' => ['required', 'exists:locations,id'],
        ]);

        try {
            $this->purchaseOrders->receive($line, (string) $validated['qty'], (int) $validated['receiving_location_id']);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.purchase.orders.show', $line->purchase_order_id)->with('status', __('Received.'));
    }
}
```

Route (`Modules/Purchase/routes/web.php` — TAMAMEN değiştir):

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\PurchaseOrderController;

Route::middleware(['auth:web'])->prefix('app/purchase')->name('app.purchase.')->group(function (): void {
    Route::middleware('permission:create purchase orders,web')->group(function (): void {
        Route::get('/orders', [PurchaseOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{po}', [PurchaseOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{po}/lines', [PurchaseOrderController::class, 'storeLine'])->name('orders.lines.store');
        Route::post('/orders/{po}/send-rfq', [PurchaseOrderController::class, 'sendRfq'])->name('orders.send-rfq');
        Route::post('/orders/{po}/cancel', [PurchaseOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/lines/{line}/receive', [PurchaseOrderController::class, 'receive'])->name('lines.receive');
    });

    Route::middleware('permission:confirm purchase orders,web')->group(function (): void {
        Route::post('/orders/{po}/confirm', [PurchaseOrderController::class, 'confirm'])->name('orders.confirm');
    });
});
```

`orders/index.blade.php`: `@extends('app.layouts.app')`, üstte "Yeni Satınalma Siparişi" butonu (modal: tedarikçi select + submit → `orders.store`), tablo: Tedarikçi, Durum rozeti (draft=gri, rfq_sent=sarı, confirmed=mavi, done=yeşil, cancelled=kırmızı — `adjustments/show.blade.php`'deki rozet class deseni), Satır Sayısı, Oluşturan, "Görüntüle" linki (`orders.show`).

`orders/show.blade.php` — `adjustments/show.blade.php`'yi REFERANS AL (durum rozeti başlıkta, alt kısımda duruma göre aksiyon butonları):
- `status='draft'`: satır ekleme formu (product/uom/qty/unit_price select+input, `orders.lines.store`'a POST), "RFQ Gönder" butonu (`orders.send-rfq`), "İptal Et" butonu (`orders.cancel`)
- `status='rfq_sent'`: `@if($canConfirm)` "Onayla" butonu (`orders.confirm`) `@else` `{{ __('Waiting for confirmation from another user (you cannot confirm your own purchase order).') }}` mesajı, "İptal Et" butonu
- `status='confirmed'`: her satır için (henüz tam teslim alınmamışsa, yani `$line->receivedQty() < $line->qty`) inline "Teslim Al" formu (qty + receiving_location_id select, `lines.receive`'a POST), "İptal Et" butonu
- `status='done'|'cancelled'`: yalnızca salt-okunur satır tablosu, aksiyon yok
- Satır tablosunda her satırda: Ürün, Miktar, Birim Fiyat, Teslim Alınan (`$line->receivedQty()`)

Menü (yeni bir grup, Inventory grubundan sonra):

```blade
                        @if (auth()->user()?->can('create purchase orders') || auth()->user()?->can('confirm purchase orders'))
                            <li class="menu-title" aria-disabled="true"><span>{{ __('Purchasing') }}</span></li>
                            <li>
                                <a href="{{ route('app.purchase.orders.index') }}" class="{{ request()->routeIs('app.purchase.orders.*') ? 'active' : '' }}">
                                    <i class="ph-duotone ph-shopping-cart"></i><span>{{ __('Purchase Orders') }}</span>
                                </a>
                            </li>
                        @endif
```

- [ ] Eski placeholder route/controller/view sil, yenilerini ekle
- [ ] Menü öğesi ekle
- [ ] `PurchaseOrderScreensTest`: sipariş oluşturma; satır ekleme; RFQ gönderme; kendi siparişini onaylayamama (403/hata mesajı görünür, redirect yok); başka kullanıcı onaylayabiliyor; onaylı siparişte teslim alma stok hareketi üretiyor (`assertDatabaseHas('stock_moves', ...)`); iptal
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=PurchaseOrderScreensTest`
- [ ] Commit: `feat(purchase): purchase order screens (lifecycle, lines, receipt)`

### Task 3: Satış Sipariş ekranı (Modules/Sales)

**Dosyalar:** Task 2 ile birebir simetrik.
- Delete/replace: `Modules/Sales/app/Http/Controllers/SalesController.php`, `Modules/Sales/routes/web.php`, `Modules/Sales/resources/views/index.blade.php`
- Create: `Modules/Sales/app/Http/Controllers/SalesOrderController.php`
- Create: `Modules/Sales/resources/views/orders/index.blade.php`, `orders/show.blade.php`
- Modify: `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Sales/SalesOrderScreensTest.php`

**Interfaces:** `SalesOrderService::create(int $tenantId, int $partnerId, int $locationId, User $creator): SalesOrder`, `addLine(SalesOrder, int $productId, int $uomId, string $qty, string $unitPrice): SalesOrderLine`, `sendQuotation(SalesOrder): void`, `confirm(SalesOrder, User $approver): void`, `cancel(SalesOrder): void`, `deliver(SalesOrderLine, string $qty): void` (hepsi Faz 8'de tanımlandı, `Modules/Sales/app/Services/SalesOrderService.php`).

`SalesOrderController` (Task 2'nin `PurchaseOrderController`'ıyla AYNI YAPIDA, farklar): `store()` hem `partner_id` HEM `location_id` alır (SO'da rezervasyon/teslimat lokasyonu sipariş seviyesinde sabit — Faz 8 kararı); `receive` yerine `deliver` (yalnızca `qty` alır, lokasyon SO'dan geliyor); confirm izni `confirm sales orders`, create izni `create sales orders`; müşteri listesi `Partner::where('is_customer', true)`.

```php
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'exists:partners,id'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $so = $this->salesOrders->create(
            $request->user()->tenant_id,
            (int) $validated['partner_id'],
            (int) $validated['location_id'],
            $request->user(),
        );

        return redirect()->route('app.sales.orders.show', $so)->with('status', __('Sales order created.'));
    }
```

```php
    public function deliver(Request $request, SalesOrderLine $line): RedirectResponse
    {
        $validated = $request->validate(['qty' => ['required', 'numeric', 'gt:0']]);

        try {
            $this->salesOrders->deliver($line, (string) $validated['qty']);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.sales.orders.show', $line->sales_order_id)->with('status', __('Delivered.'));
    }
```

Route isimleri: `app.sales.orders.index/store/show/lines.store/send-quotation/cancel`, `app.sales.lines.deliver`; izinler `create sales orders` (index/store/show/lines/send-quotation/cancel/deliver), `confirm sales orders` (confirm).

`orders/show.blade.php` durum makinesi Task 2 ile aynı desende: `draft` (satır ekle + teklif gönder + iptal), `quotation_sent` (`$canConfirm` ise onayla, değilse mesaj + iptal), `confirmed` (her teslim edilmemiş satırda inline "Teslim Et" formu — yalnızca `qty` input, lokasyon YOK çünkü SO'nun kendi `location_id`'si kullanılıyor + iptal), `done`/`cancelled` (salt okunur). Satır tablosunda: Ürün, Miktar, Birim Fiyat, Teslim Edilen (`$line->delivered_qty`).

Menü (Purchasing grubundan sonra):

```blade
                        @if (auth()->user()?->can('create sales orders') || auth()->user()?->can('confirm sales orders'))
                            <li class="menu-title" aria-disabled="true"><span>{{ __('Sales') }}</span></li>
                            <li>
                                <a href="{{ route('app.sales.orders.index') }}" class="{{ request()->routeIs('app.sales.orders.*') ? 'active' : '' }}">
                                    <i class="ph-duotone ph-receipt"></i><span>{{ __('Sales Orders') }}</span>
                                </a>
                            </li>
                        @endif
```

- [ ] Eski placeholder sil, yenilerini ekle
- [ ] Menü öğesi ekle
- [ ] `SalesOrderScreensTest`: oluşturma (partner+location); satır ekleme; teklif gönderme; kendi siparişini onaylayamama; başka kullanıcı onaylayabiliyor; teslimat (stok düşüşü + rezerv serbest kalması, `assertDatabaseHas('stock_moves', ...)` + quant assertion); iptal
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=SalesOrderScreensTest`
- [ ] Commit: `feat(sales): sales order screens (lifecycle, lines, delivery)`

### Task 4: Putaway Kuralları ekranı (Inventory)

**Dosyalar:**
- Create: `Modules/Inventory/app/Http/Controllers/PutawayRuleController.php`
- Create: `Modules/Inventory/resources/views/putaway/index.blade.php`, `putaway/_form-modal.blade.php`
- Modify: `Modules/Inventory/routes/web.php`, `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Inventory/PutawayRuleScreensTest.php`

PRD notu (bu proje hafızasında doğrulandı): `manage routes` izni hem rota HEM putaway kuralı tanımlamayı kapsar — ayrı bir izin YOK.

```php
<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\PutawayRule;

class PutawayRuleController extends Controller
{
    public function index(): View
    {
        return view('inventory::putaway.index', [
            'rules' => PutawayRule::with(['product', 'productCategory', 'sourceLocation', 'destLocation'])->orderBy('sequence')->get(),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['nullable', 'exists:products,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'source_location_id' => ['required', 'exists:locations,id'],
            'dest_location_id' => ['required', 'exists:locations,id', 'different:source_location_id'],
            'sequence' => ['required', 'integer', 'min:0'],
        ]);

        PutawayRule::create($validated);

        return redirect()->route('app.inventory.putaway.index')->with('status', __('Putaway rule added.'));
    }

    public function destroy(PutawayRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('app.inventory.putaway.index')->with('status', __('Putaway rule deleted.'));
    }
}
```

Route: `permission:manage routes,web` grubunda `GET /putaway` (`putaway.index`), `POST /putaway` (`putaway.store`), `DELETE /putaway/{rule}` (`putaway.destroy`).

View: `warehouses/index.blade.php`'nin genel tablo+modal iskeletini referans al. Form: ürün VEYA kategori seçimi (radio ile ikisinden biri, JS gerekmez — iki ayrı select, boş bırakılan `nullable` olarak gönderilir), kaynak/hedef lokasyon select, sıra (sequence) number input. Tablo kolonları: Ürün/Kategori (biri null ise diğerini göster), Kaynak, Hedef, Sıra, Sil butonu (`onsubmit="return confirm(...)"` deseni, bkz. `templates/index.blade.php`).

Menü (Inventory grubu içine, Transfers'ten sonra, Partners'tan önce ya da sonra — sırası önemli değil):

```blade
                            @can('manage routes')
                                <li>
                                    <a href="{{ route('app.inventory.putaway.index') }}" class="{{ request()->routeIs('app.inventory.putaway.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-map-pin-line"></i><span>{{ __('Putaway Rules') }}</span>
                                    </a>
                                </li>
                            @endcan
```
(üstteki Inventory `@if` satırına `|| auth()->user()?->can('manage routes')` ekle.)

- [ ] Controller/route/view/menü ekle
- [ ] `PutawayRuleScreensTest`: liste sayfası kuralları gösterir; ürün-bazlı kural oluşturma; kategori-bazlı kural oluşturma; silme; izinsiz kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=PutawayRuleScreensTest`
- [ ] Commit: `feat(inventory): putaway rule management screen`

### Task 5: Yeniden Sipariş Kuralları + Öneriler ekranı (Inventory)

**Dosyalar:**
- Create: `Modules/Inventory/app/Http/Controllers/ReorderingRuleController.php`
- Create: `Modules/Inventory/resources/views/reordering/index.blade.php`, `reordering/_form-modal.blade.php`
- Modify: `Modules/Inventory/routes/web.php`, `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Inventory/ReorderingScreensTest.php`

**Interfaces:** `ReorderingService::acknowledge(ReplenishmentSuggestion, User $user): void` (Faz 6/7, `Modules/Inventory/app/Services/ReorderingService.php`) — çağrıldığında `ReplenishmentAcknowledged` event'i fırlar, Purchase modülündeki listener varsayılan tedarikçiye draft PO oluşturur (ürünün `default_supplier_id`'si varsa).

```php
<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderingRule;
use Modules\Inventory\Models\ReplenishmentSuggestion;
use Modules\Inventory\Services\ReorderingService;

class ReorderingRuleController extends Controller
{
    public function __construct(private readonly ReorderingService $reordering) {}

    public function index(): View
    {
        return view('inventory::reordering.index', [
            'rules' => ReorderingRule::with(['product', 'location'])->get(),
            'suggestions' => ReplenishmentSuggestion::with('reorderingRule.product')->where('status', 'pending')->latest()->get(),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'max_qty' => ['required', 'numeric', 'gt:min_qty'],
            'trigger_type' => ['required', 'in:auto,manual'],
        ]);

        ReorderingRule::create($validated);

        return redirect()->route('app.inventory.reordering.index')->with('status', __('Reordering rule added.'));
    }

    public function destroy(ReorderingRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('app.inventory.reordering.index')->with('status', __('Reordering rule deleted.'));
    }

    public function acknowledge(Request $request, ReplenishmentSuggestion $suggestion): RedirectResponse
    {
        $this->reordering->acknowledge($suggestion, $request->user());

        return redirect()->route('app.inventory.reordering.index')->with('status', __('Suggestion acknowledged; a draft purchase order may have been created if the product has a default supplier.'));
    }
}
```

Route: `permission:manage reordering rules,web` grubunda `GET /reordering` (`reordering.index`), `POST /reordering` (`reordering.store`), `DELETE /reordering/{rule}` (`reordering.destroy`), `POST /reordering/suggestions/{suggestion}/acknowledge` (`reordering.suggestions.acknowledge`).

View: iki tablo tek sayfada. (1) Kurallar tablosu (Ürün, Lokasyon, Min, Max, Tetikleme Tipi, Sil) + "Yeni Kural" modalı. (2) Bekleyen Öneriler tablosu (Ürün, Önerilen Miktar, "Onayla" butonu `POST reordering.suggestions.acknowledge`, `onsubmit="return confirm(...)"`).

Menü (Inventory grubu, Putaway'den sonra):

```blade
                            @can('manage reordering rules')
                                <li>
                                    <a href="{{ route('app.inventory.reordering.index') }}" class="{{ request()->routeIs('app.inventory.reordering.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-arrows-clockwise"></i><span>{{ __('Reordering') }}</span>
                                    </a>
                                </li>
                            @endcan
```
(üstteki Inventory `@if` satırına `|| auth()->user()?->can('manage reordering rules')` ekle.)

- [ ] Controller/route/view/menü ekle
- [ ] `ReorderingScreensTest`: kural oluşturma; bekleyen önerinin listelendiği; "Onayla" aksiyonunun `reordering_rules`/`replenishment_suggestions` durumunu `acknowledged`'e çevirdiği (event fırlatıldığı için Faz 7 listener'ı da tetiklenebilir — test yalnızca suggestion durumunu doğrular, PO oluşumu Faz 7'nin kendi testlerinde zaten kanıtlı); silme; izinsiz kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=ReorderingScreensTest`
- [ ] Commit: `feat(inventory): reordering rule and replenishment suggestion screens`

### Task 6: Rotalar (Routes) ekranı (Inventory)

**Dosyalar:**
- Create: `Modules/Inventory/app/Http/Controllers/RouteController.php`
- Create: `Modules/Inventory/resources/views/routes/index.blade.php`, `routes/show.blade.php`
- Modify: `Modules/Inventory/routes/web.php`, `resources/views/app/layouts/app.blade.php`
- Test: `tests/Feature/Inventory/RouteScreensTest.php`

**Interfaces:** `RouteService::executePush(Route $route, Product $product, string $qty, Uom $uom): void` (Faz 6, `Modules/Inventory/app/Services/RouteService.php`) — yalnızca `action='push'` adımlarını sırayla zincirler.

**NOT:** Dosya adı çakışması var — `Modules\Inventory\Models\Route` PHP sınıfı ile bu controller'ın kullanacağı Laravel `Illuminate\Support\Facades\Route` facade'i AYNI KISA İSME sahip. Controller'da facade'i `use Illuminate\Support\Facades\Route as RouteFacade;` gibi import ETMEYECEĞİZ çünkü controller İÇİNDE facade kullanılmıyor (yalnızca modelle çalışıyor) — yalnızca `Modules/Inventory/routes/web.php` dosyasında (orada zaten `use Illuminate\Support\Facades\Route;` var ve model hiç import edilmiyor, çakışma yok, model her yerde tam nitelenmiş `\Modules\Inventory\Models\Route` olarak veya route model binding'de type-hint olarak kullanılacak).

```php
<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Route as RouteModel;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Services\RouteService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RouteController extends Controller
{
    public function __construct(private readonly RouteService $routes) {}

    public function index(): View
    {
        return view('inventory::routes.index', [
            'routes' => RouteModel::withCount('rules')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $route = RouteModel::create($validated);

        return redirect()->route('app.inventory.routes.show', $route)->with('status', __('Route created.'));
    }

    public function show(RouteModel $route): View
    {
        return view('inventory::routes.show', [
            'route' => $route->load(['rules.fromLocation', 'rules.toLocation']),
            'locations' => Location::where('type', 'internal')->orderBy('name')->get(),
            'products' => Product::where('product_type', '!=', 'service')->orderBy('name')->get(),
            'uoms' => Uom::orderBy('name')->get(),
        ]);
    }

    public function storeRule(Request $request, RouteModel $route): RedirectResponse
    {
        $validated = $request->validate([
            'from_location_id' => ['required', 'exists:locations,id'],
            'to_location_id' => ['required', 'exists:locations,id', 'different:from_location_id'],
            'action' => ['required', 'in:push,pull'],
            'sequence' => ['required', 'integer', 'min:0'],
        ]);

        $route->rules()->create($validated);

        return redirect()->route('app.inventory.routes.show', $route)->with('status', __('Route rule added.'));
    }

    public function execute(Request $request, RouteModel $route): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'uom_id' => ['required', 'exists:uoms,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $uom = Uom::findOrFail($validated['uom_id']);

        try {
            $this->routes->executePush($route, $product, (string) $validated['qty'], $uom);
        } catch (HttpException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('app.inventory.routes.show', $route)->with('status', __('Push route executed.'));
    }
}
```

Route: `permission:manage routes,web` grubunda `GET /routes` (`routes.index`), `POST /routes` (`routes.store`), `GET /routes/{route}` (`routes.show`), `POST /routes/{route}/rules` (`routes.rules.store`), `POST /routes/{route}/execute` (`routes.execute`).

`routes/index.blade.php`: rota listesi (Ad, Kural Sayısı, Görüntüle linki) + "Yeni Rota" modalı (yalnızca isim).
`routes/show.blade.php`: rota kuralları tablosu (Sıra, Kaynak, Hedef, Aksiyon push/pull rozeti) sıra artan sırada + "Kural Ekle" formu (kaynak/hedef lokasyon select, aksiyon select, sıra) + ayrı bir "Push Rotasını Çalıştır" formu (ürün select, birim select, miktar input → `routes.execute`) test/manuel tetikleme amaçlı.

Menü (Inventory grubu, Reordering'den sonra):

```blade
                            @can('manage routes')
                                <li>
                                    <a href="{{ route('app.inventory.routes.index') }}" class="{{ request()->routeIs('app.inventory.routes.*') ? 'active' : '' }}">
                                        <i class="ph-duotone ph-flow-arrow"></i><span>{{ __('Routes') }}</span>
                                    </a>
                                </li>
                            @endcan
```
(Bu `@can('manage routes')` bloğu Task 4'te Putaway için de eklenmişti — aynı izin iki menü öğesini açar, bu BEKLENEN davranıştır, üstteki `@if` satırına tekrar ekleme YAPMA, zaten Task 4'te eklendi.)

- [ ] Controller/route/view/menü ekle
- [ ] `RouteScreensTest`: rota oluşturma; kural ekleme (sıra sırasına göre listeleniyor); push rotasını çalıştırma → doğru zincirleme stok hareketleri oluşuyor (2 adımlı rota testinde son lokasyonda doğru miktar, bkz. `tests/Feature/Inventory/RouteServiceTest.php`'deki senaryo aynı HTTP üzerinden); izinsiz kullanıcı 403
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=RouteScreensTest`
- [ ] Commit: `feat(inventory): route and route rule management screens with push execution`

## Self-Review
- Faz 6/7/8'de ekranı eksik kalan HER özellik kapsandı: Partner (Faz7), Satınalma sipariş akışı (Faz7), Satış sipariş akışı (Faz8), Putaway/Reordering/Routes (Faz6).
- `Modules/Sales`/`Modules/Purchase`'ın kullanılmayan nwidart iskelet dosyaları (placeholder controller/route/view) SİLİNİYOR, yamanmıyor — yarım kalan "Hello World" kodu bırakmamak için.
- Modül sınırı ihlali (Inventory'nin Sales/Purchase'a bağımlı olması) BİLİNÇLİ olarak engellendi: Partner ekranında silme (destroy) yok.
- "Yeniden sipariş önerisini reddet/dismiss" aksiyonu KAPSAM DIŞI bırakıldı — `ReorderingService`'te böyle bir metod hiç yok (yalnızca `acknowledge()` var), bu görev yalnızca EKRAN eksikliğini kapatıyor, yeni iş mantığı eklemiyor. İstenirse ayrı bir görev olarak ele alınmalı.
- Her görev kendi HTTP feature testiyle kanıtlanıyor (Laravel Boost kuralı: "Every change must be programmatically tested").
