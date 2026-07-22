# Faz 9: Muhasebe Çekirdeği — Hesap Planı, Yevmiye, Fatura, Ödeme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans veya superpowers:subagent-driven-development.

**Goal:** PRD 3.12/4.1.4 — çift taraflı (double-entry) muhasebe çekirdeği: her satınalma teslim alımı, her satış teslimatı, her fatura onayı ve her ödeme/tahsilat otomatik olarak dengeli bir `journal_entries`+`journal_entry_lines` seti üretir; 3 yönlü eşleştirme (PO/SO ↔ receipt/delivery ↔ invoice) ve kısmi tahsilat/ödeme dağıtımı çalışır. Kabul kriteri: alım→teslim alım→fatura→ödeme VE satış→teslimat→fatura→tahsilat zincirlerinin her ikisi de baştan sona doğru, dengeli bir yevmiye seti üretir.

**Architecture — bu fazın kilit mimari kararları (implementer'lar bunları AYNEN uygulamalı, PRD'nin kendisi bu düzeyde detay vermiyor, aşağıdaki tasarım PRD'nin 4-hesap (`product_categories`) + `tax_rates.tax_account_id` + morphs şemasından türetilen TEK tutarlı, dengesi kanıtlanmış modeldir):**

1. **Hesap kodu sözleşmesi (Tekdüzen Hesap Planı standart kodları, `AccountingDefaultsService` tarafından her tenant için seed edilir):**
   `100` Kasa (asset), `102` Bankalar (asset), `120` Alıcılar (asset, müşteri alacağı), `153` Ticari Mallar (asset, stok), `191` İndirilecek KDV (asset), `320` Satıcılar (liability, tedarikçi borcu), `391` Hesaplanan KDV (liability), `600` Yurtiçi Satışlar (income), `621` Satılan Ticari Mallar Maliyeti (expense, COGS), `646` Kambiyo Karları (income, Faz 10'da kullanılacak), `656` Kambiyo Zararları (expense, Faz 10'da kullanılacak). Bu kodlar Türkiye'de YASAL OLARAK standart olduğundan (bu PRD'nin "Tekdüzen" — uniform — dediği tam da budur), `JournalEntryService` bunlara KOD ÜZERİNDEN (`ChartOfAccount::where('tenant_id',...)->where('code','120')->firstOrFail()`) başvurabilir — bu bir "hack" değil, standardın doğru kullanımıdır.
2. **`product_categories`'in 4 hesabı** (`stock_input_account_id`, `stock_output_account_id`, `expense_account_id`, `income_account_id`) yalnızca ÜRÜNE ÖZGÜ tarafı taşır (hangi stok/gider/gelir hesabı); Alıcılar(120)/Satıcılar(320)/KDV hesapları HER ZAMAN yukarıdaki standart kodlardan veya `tax_rates.tax_account_id`'den gelir.
3. **Satınalma teslim alımı (`PurchaseOrderService::receive()`) → `PurchaseOrderLineReceived` event → `postForPurchaseReceipt`:** net mal değeri (qty × `unit_price`) kadar, `accounting_mode`'a göre: **anglo_saxon** → dr `stock_input_account_id`(153) / cr Satıcılar(320); **continental** → dr `expense_account_id`(621) / cr Satıcılar(320) (PRD: "Continental modda stok girişinde doğrudan gider hesabına yazılır"). Bu, satınalma zincirinin NET mal değerini Satıcılar'a (320) borç olarak yazar; fatura onayı yalnızca KDV kısmını EKLER (aşağıya bkz.) — böylece net değer İKİ KEZ kaydedilmez.
4. **Satış teslimatı (`SalesOrderService::deliver()`) → `SalesOrderLineDelivered` event (COGS tutarını TAŞIR — `CostingService::consumeOutbound()`'un şu an atılan dönüş değeri artık event'e eklenir) → `postForSalesDelivery`:** yalnızca **anglo_saxon** modda dr `expense_account_id`(621, COGS) / cr `stock_output_account_id`(153); **continental modda HİÇBİR KAYIT ÜRETİLMEZ** (continental zaten alım anında tüm değeri gider yazdığından, satışta COGS'u tekrar tanımak çifte sayım olurdu — bu PRD'nin "Anglo-Saxon'da COGS satışta, Continental'da girişte" ayrımının doğrudan sonucu).
5. **Fatura onayı (`InvoiceService::post()`) → `postForInvoice`:** satınalma faturası → dr İndirilecek KDV(191) [yalnızca vergi tutarı] / cr Satıcılar(320) [yalnızca vergi tutarı] (net mal değeri zaten adım 3'te kaydedildi); satış faturası → dr Alıcılar(120) [brüt = net+KDV] / cr `income_account_id`(600) [net] / cr Hesaplanan KDV(391) [KDV] (gelir tanıma, satış faturasında YAPILIR — teslimatta değil).
6. **Ödeme/tahsilat (`PaymentService::allocate()`) → `postForPayment`:** her `payment_allocations` satırı kendi dengeli kaydını üretir — tedarikçiye ödeme: dr Satıcılar(320) / cr [journal'ın kasa/banka hesabı, `journals.type`'a göre 100 veya 102]; müşteriden tahsilat: dr [100/102] / cr Alıcılar(120). Fatura `remainingBalance()` sıfırlanınca `invoices.status='paid'`.
7. **Otomatik muhasebe kaydı BEST-EFFORT'tur, ZORUNLU DEĞİL:** eğer ürünün `product_category_id`'si null'sa VEYA kategorinin ilgili hesap kolonu null'sa, ilgili `Create*Listener` SESSİZCE hiçbir şey yapmadan döner (istisna FIRLATMAZ) — bu hem "Muhasebe modülü her tenant'ta aktif olmayabilir" gerçeğiyle (modül aktivasyonu bu fazda ayrıca kontrol edilmiyor, ama muhasebe yapılandırılmamış bir tenant'ın satınalma/satış akışını KİLİTLEMEMESİ gerekir) hem de mevcut Faz 7/8 testlerinin (ürün factory'leri `product_category_id` atamıyor) KIRILMAMASIYLA doğrudan ilgilidir. Bu davranış `postForPurchaseReceipt`/`postForSalesDelivery` içinde uygulanır (postForInvoice/postForPayment için kategori kontrolü YOK — bunlar Accountant'ın elle tetiklediği aksiyonlardır, hesap eksikse 422 ile GERÇEK bir hata döner, sessizce atlanmaz — fark önemli: otomatik/arka plan olaylar sessiz atlar, kullanıcının elle çağırdığı servis aksiyonları 422 döner).
8. **Modül bağımlılığı:** `Modules/Accounting/module.json`'un `requires` listesine `Purchase` ve `Sales` eklenir (zaten `Inventory` var) — Accounting, Purchase/Sales'in event'lerini dinlediği ve modellerini kullandığı için bu yöne bağımlıdır; TERSİ asla olmaz (Purchase/Sales, Accounting'i hiç import etmez, yalnızca event fırlatır).
9. **3 Yönlü Eşleştirme** yalnızca satınalma faturasında uygulanır (PRD 3.10): `InvoiceService::addLine()` bir purchase invoice için çağrıldığında, aynı üründe `purchase_order_lines.qty` (sipariş edilen) ve `bill_control_policy='received_qty'` ise `PurchaseOrderLine::receivedQty()` (teslim alınan) ile bu faturaya (VE AYNI PO'nun diğer faturalarına) kümülatif olarak faturalanan miktar karşılaştırılır; aşım 422.

## Global Constraints
- Master plan + önceki tüm faz kısıtları geçerli (bcmath her yerde, float YOK; `BelongsToTenant`; morph map kısa alias zorunlu; `DB::raw`/trigger yasak — DENGE kontrolü SERVİS KATMANINDA yapılır).
- Yeni tablolarda FK constraint YOK (bu projenin tüm önceki fazlarındaki tutarlı konvansiyon — `unsignedBigInteger` + index yeterli).
- Bu faz EKRAN İÇERMİYOR (Faz 5/6/7 gibi yalnızca motor+model+test; ekranlar ayrı bir sonraki round'da ele alınabilir).
- Model isimlendirme: `chart_of_accounts` tablosu → `ChartOfAccount` modeli (Eloquent'in otomatik snake+plural çözümlemesi `chart_of_accounts`'a birebir eşleşir, `$table` override GEREKMEZ).
- Yeni metinler `__('EN')` + `lang/tr.json`.
- `vendor/bin/pint --dirty --format agent` her PHP değişikliğinden sonra.

---

### Task 1: Migrasyonlar + modeller + factory'ler + morph map + Accountant rolü

**Dosyalar:**
- Create: `Modules/Accounting/database/migrations/2026_07_21_700001_create_chart_of_accounts_table.php`, `..._700002_create_journals_tables.php` (journals+journal_entries+journal_entry_lines), `..._700003_create_tax_rates_table.php`, `..._700004_create_invoices_tables.php` (invoices+invoice_lines), `..._700005_create_payments_tables.php` (payments+payment_allocations)
- Create: `Modules/Accounting/app/Models/ChartOfAccount.php`, `Journal.php`, `JournalEntry.php`, `JournalEntryLine.php`, `TaxRate.php`, `Invoice.php`, `InvoiceLine.php`, `Payment.php`, `PaymentAllocation.php` + `Modules/Accounting/database/factories/*` (her model için)
- Modify: `Modules/Inventory/app/Models/ProductCategory.php` (4 hesap ilişkisi eklenir), `Modules/Purchase/app/Models/PurchaseOrderLine.php` (`taxRate()`), `Modules/Sales/app/Models/SalesOrderLine.php` (`taxRate()`), `app/Providers/AppServiceProvider.php` (morph map), `Modules/Accounting/module.json` (`requires`)
- Test: `tests/Feature/Accounting/AccountingModelTest.php`

Migration'lar PRD 4.1.4'e BİREBİR (FK yok, tenant_id + ilgili index'ler):

```php
// create_chart_of_accounts_table.php
Schema::create('chart_of_accounts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->string('code');
    $table->string('name');
    $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
    $table->timestamps();
    $table->unique(['tenant_id', 'code']);
});
```

```php
// create_journals_tables.php
Schema::create('journals', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->string('name');
    $table->enum('type', ['sale', 'purchase', 'cash', 'bank', 'stock', 'general']);
    $table->timestamps();
});

Schema::create('journal_entries', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->unsignedBigInteger('journal_id');
    $table->date('entry_date');
    $table->morphs('reference'); // stock_move | invoice | payment
    $table->enum('status', ['draft', 'posted', 'cancelled']);
    $table->timestamps();

    $table->index(['tenant_id', 'journal_id']);
});

Schema::create('journal_entry_lines', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->unsignedBigInteger('journal_entry_id');
    $table->unsignedBigInteger('account_id');
    $table->decimal('debit', 15, 4)->default(0);
    $table->decimal('credit', 15, 4)->default(0);
    $table->timestamps();

    $table->index(['tenant_id', 'account_id']);
});
```

```php
// create_tax_rates_table.php
Schema::create('tax_rates', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->string('name');
    $table->decimal('percentage', 5, 2);
    $table->enum('type', ['sale', 'purchase']);
    $table->unsignedBigInteger('tax_account_id');
    $table->timestamps();
});
```

```php
// create_invoices_tables.php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->unsignedBigInteger('partner_id');
    $table->enum('type', ['purchase', 'sale']);
    $table->morphs('source'); // purchase_order | sales_order
    $table->enum('status', ['draft', 'posted', 'paid', 'cancelled']);
    $table->timestamps();

    $table->index(['tenant_id', 'partner_id']);
});

Schema::create('invoice_lines', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->unsignedBigInteger('invoice_id');
    $table->unsignedBigInteger('product_id');
    $table->decimal('qty', 15, 4);
    $table->decimal('unit_price', 15, 4);
    $table->unsignedBigInteger('tax_rate_id')->nullable();
    $table->timestamps();

    $table->index(['tenant_id', 'invoice_id']);
});
```

```php
// create_payments_tables.php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->unsignedBigInteger('partner_id');
    $table->unsignedBigInteger('journal_id');
    $table->decimal('amount', 15, 4);
    $table->date('payment_date');
    $table->timestamps();
});

Schema::create('payment_allocations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->unsignedBigInteger('payment_id');
    $table->unsignedBigInteger('invoice_id');
    $table->decimal('allocated_amount', 15, 4);
    $table->timestamps();

    $table->index(['tenant_id', 'payment_id']);
    $table->index(['tenant_id', 'invoice_id']);
});
```

Modeller (`Modules/Purchase/app/Models/PurchaseOrder.php`/`PurchaseOrderLine.php` desenindeki `BelongsToTenant, HasFactory` + `newFactory()` + fillable + `decimal:4` cast deseninde yaz — bu dosyaları AÇIP referans al):

```php
// Modules/Accounting/app/Models/ChartOfAccount.php
class ChartOfAccount extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'code', 'name', 'type'];

    protected static function newFactory(): ChartOfAccountFactory { return ChartOfAccountFactory::new(); }
}
```

```php
// Modules/Accounting/app/Models/Journal.php
class Journal extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'type'];

    protected static function newFactory(): JournalFactory { return JournalFactory::new(); }
}
```

```php
// Modules/Accounting/app/Models/JournalEntry.php
class JournalEntry extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'journal_id', 'entry_date', 'status'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    protected static function newFactory(): JournalEntryFactory { return JournalEntryFactory::new(); }

    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function lines(): HasMany { return $this->hasMany(JournalEntryLine::class); }
    public function reference(): MorphTo { return $this->morphTo(); }
}
```

```php
// Modules/Accounting/app/Models/JournalEntryLine.php
class JournalEntryLine extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'journal_entry_id', 'account_id', 'debit', 'credit'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:4', 'credit' => 'decimal:4'];
    }

    protected static function newFactory(): JournalEntryLineFactory { return JournalEntryLineFactory::new(); }

    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }
}
```

```php
// Modules/Accounting/app/Models/TaxRate.php
class TaxRate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'percentage', 'type', 'tax_account_id'];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2'];
    }

    protected static function newFactory(): TaxRateFactory { return TaxRateFactory::new(); }

    public function taxAccount(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'tax_account_id'); }
}
```

```php
// Modules/Accounting/app/Models/Invoice.php
class Invoice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'partner_id', 'type', 'status'];

    protected static function newFactory(): InvoiceFactory { return InvoiceFactory::new(); }

    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
    public function lines(): HasMany { return $this->hasMany(InvoiceLine::class); }
    public function source(): MorphTo { return $this->morphTo(); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }

    public function subtotal(): string
    {
        return $this->lines->reduce(fn (string $carry, InvoiceLine $line) => bcadd($carry, $line->subtotal(), 4), '0.0000');
    }

    public function taxTotal(): string
    {
        return $this->lines->reduce(fn (string $carry, InvoiceLine $line) => bcadd($carry, $line->taxAmount(), 4), '0.0000');
    }

    public function total(): string
    {
        return bcadd($this->subtotal(), $this->taxTotal(), 4);
    }

    public function paidTotal(): string
    {
        return (string) $this->allocations()->sum('allocated_amount');
    }

    public function remainingBalance(): string
    {
        return bcsub($this->total(), $this->paidTotal(), 4);
    }
}
```
(`Partner` importu: `Modules\Inventory\Models\Partner` — Accounting zaten Inventory'e bağımlı.)

```php
// Modules/Accounting/app/Models/InvoiceLine.php
class InvoiceLine extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'invoice_id', 'product_id', 'qty', 'unit_price', 'tax_rate_id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'unit_price' => 'decimal:4'];
    }

    protected static function newFactory(): InvoiceLineFactory { return InvoiceLineFactory::new(); }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function taxRate(): BelongsTo { return $this->belongsTo(TaxRate::class); }

    public function subtotal(): string
    {
        return bcmul($this->qty, $this->unit_price, 4);
    }

    public function taxAmount(): string
    {
        if ($this->tax_rate_id === null) {
            return '0.0000';
        }

        return bcmul($this->subtotal(), bcdiv($this->taxRate->percentage, '100', 6), 4);
    }
}
```
(`Product` importu: `Modules\Inventory\Models\Product`.)

```php
// Modules/Accounting/app/Models/Payment.php
class Payment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'partner_id', 'journal_id', 'amount', 'payment_date'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'payment_date' => 'date'];
    }

    protected static function newFactory(): PaymentFactory { return PaymentFactory::new(); }

    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }

    public function allocatedTotal(): string
    {
        return (string) $this->allocations()->sum('allocated_amount');
    }

    public function unallocatedAmount(): string
    {
        return bcsub($this->amount, $this->allocatedTotal(), 4);
    }
}
```

```php
// Modules/Accounting/app/Models/PaymentAllocation.php
class PaymentAllocation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'payment_id', 'invoice_id', 'allocated_amount'];

    protected function casts(): array
    {
        return ['allocated_amount' => 'decimal:4'];
    }

    protected static function newFactory(): PaymentAllocationFactory { return PaymentAllocationFactory::new(); }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
```

`Modules/Inventory/app/Models/ProductCategory.php`'ye eklenecek ilişkiler:

```php
    public function stockInputAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\ChartOfAccount::class, 'stock_input_account_id');
    }

    public function stockOutputAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\ChartOfAccount::class, 'stock_output_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\ChartOfAccount::class, 'expense_account_id');
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\ChartOfAccount::class, 'income_account_id');
    }
```

`PurchaseOrderLine`/`SalesOrderLine`'a eklenecek (her ikisine de aynı metod):

```php
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(\Modules\Accounting\Models\TaxRate::class);
    }
```

`app/Providers/AppServiceProvider.php::configureMorphMap()`'e eklenecek (importlar + array):

```php
'purchase_order' => \Modules\Purchase\Models\PurchaseOrder::class,
'sales_order' => \Modules\Sales\Models\SalesOrder::class,
'invoice' => \Modules\Accounting\Models\Invoice::class,
'payment' => \Modules\Accounting\Models\Payment::class,
```

`Modules/Accounting/module.json`'daki `requires` dizisine `"Purchase"` ve `"Sales"` eklenir (zaten `"Inventory"` var).

Accountant rolü: `App\Support\PermissionCatalog`'un `accounting` grubu ZATEN mevcut (`manage chart of accounts`, `post journal entries`, `register payments`) — yeni bir metod ekle:

```php
    /**
     * @return list<string>
     */
    public static function accountantDefaults(): array
    {
        return ['manage chart of accounts', 'post journal entries', 'register payments'];
    }
```

`database/seeders/RoleSeeder.php`'ye: `Role::findOrCreate('Accountant', 'web');` (Sales Representative satırının altına). `database/seeders/PermissionSeeder.php`'ye: `Role::findByName('Accountant', 'web')->syncPermissions(PermissionCatalog::accountantDefaults());`

- [ ] Migration'lar + modeller + factory'ler yaz; `php artisan migrate --no-interaction`
- [ ] `ProductCategory`/`PurchaseOrderLine`/`SalesOrderLine` ilişkilerini ekle
- [ ] Morph map + `module.json` + Accountant rolü/izinleri güncelle
- [ ] `AccountingModelTest`: her modelin ilişkileri doğru çalışıyor; `Invoice::total()`/`remainingBalance()` doğru hesaplıyor (2 satırlı, KDV'li bir örnek); `Payment::unallocatedAmount()` doğru
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=AccountingModelTest`
- [ ] Commit: `feat(accounting): chart of accounts, journals, invoices, payments data model`

### Task 2: AccountingDefaultsService — Tekdüzen Hesap Planı + Journal + KDV seed'i

**Dosyalar:**
- Create: `Modules/Accounting/app/Services/AccountingDefaultsService.php`
- Modify: `app/Services/Platform/TenantProvisioningService.php`
- Test: `tests/Feature/Accounting/AccountingDefaultsServiceTest.php`

**Interfaces:** `InventoryDefaultsService::provision(Tenant $tenant): void` (Faz 0'dan, `TenantProvisioningService`'de zaten çağrılıyor) — AYNI desende yeni bir `AccountingDefaultsService::provision(Tenant $tenant): void` eklenir ve `TenantProvisioningService` constructor'ına + `createWithAdmin()` transaction'ına aynı yerde (inventory defaults'un hemen ardından) enjekte edilir.

```php
<?php

namespace Modules\Accounting\Services;

use App\Models\Tenant;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\TaxRate;

/**
 * Yeni tenant için Tekdüzen Hesap Planı (PRD 3.14) + yevmiye defterleri +
 * 2026 KDV oranları (PRD tax_rates seed'i). Idempotent (firstOrCreate),
 * tenant_id explicit taşınır.
 */
class AccountingDefaultsService
{
    private const ACCOUNTS = [
        ['code' => '100', 'name' => 'Kasa', 'type' => 'asset'],
        ['code' => '102', 'name' => 'Bankalar', 'type' => 'asset'],
        ['code' => '120', 'name' => 'Alıcılar', 'type' => 'asset'],
        ['code' => '153', 'name' => 'Ticari Mallar', 'type' => 'asset'],
        ['code' => '191', 'name' => 'İndirilecek KDV', 'type' => 'asset'],
        ['code' => '320', 'name' => 'Satıcılar', 'type' => 'liability'],
        ['code' => '391', 'name' => 'Hesaplanan KDV', 'type' => 'liability'],
        ['code' => '600', 'name' => 'Yurtiçi Satışlar', 'type' => 'income'],
        ['code' => '621', 'name' => 'Satılan Ticari Mallar Maliyeti', 'type' => 'expense'],
        ['code' => '646', 'name' => 'Kambiyo Karları', 'type' => 'income'],
        ['code' => '656', 'name' => 'Kambiyo Zararları', 'type' => 'expense'],
    ];

    private const JOURNALS = [
        ['name' => 'Satış', 'type' => 'sale'],
        ['name' => 'Alış', 'type' => 'purchase'],
        ['name' => 'Kasa', 'type' => 'cash'],
        ['name' => 'Banka', 'type' => 'bank'],
        ['name' => 'Stok', 'type' => 'stock'],
        ['name' => 'Genel', 'type' => 'general'],
    ];

    public function provision(Tenant $tenant): void
    {
        foreach (self::ACCOUNTS as $account) {
            ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type']],
            );
        }

        foreach (self::JOURNALS as $journal) {
            Journal::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'type' => $journal['type']],
                ['name' => $journal['name']],
            );
        }

        $purchaseTaxAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('code', '191')->firstOrFail();
        $saleTaxAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('code', '391')->firstOrFail();

        foreach (['1', '8', '20'] as $rate) {
            TaxRate::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => "KDV %{$rate} (Satış)"],
                ['percentage' => $rate, 'type' => 'sale', 'tax_account_id' => $saleTaxAccount->id],
            );
            TaxRate::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => "KDV %{$rate} (Alış)"],
                ['percentage' => $rate, 'type' => 'purchase', 'tax_account_id' => $purchaseTaxAccount->id],
            );
        }
    }

    public function accountByCode(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('code', $code)->firstOrFail();
    }
}
```

`TenantProvisioningService` değişikliği: constructor'a `private readonly AccountingDefaultsService $accountingDefaults` eklenir; `createWithAdmin()`'deki transaction içinde `$this->inventoryDefaults->provision($tenant);` satırının hemen altına `$this->accountingDefaults->provision($tenant);` eklenir.

- [ ] `AccountingDefaultsService` yaz (yukarıdaki kod)
- [ ] `TenantProvisioningService`'i güncelle
- [ ] `AccountingDefaultsServiceTest`: `provision()` çağrıldığında 11 hesap + 6 journal + 6 tax_rate oluşuyor; ikinci kez çağrıldığında (idempotency) satır sayısı ARTMIYOR; `accountByCode()` doğru hesabı döndürüyor, olmayan kod için `ModelNotFoundException`
- [ ] Mevcut tenant provisioning testini (varsa, `grep -rl "createWithAdmin" tests/` ile bul) regresyon için çalıştır — yeni hesap/journal/tax_rate satırları oluşması bu testi KIRMAMALI (yalnızca ekstra veri, mevcut assertion'lar etkilenmemeli)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=AccountingDefaultsServiceTest`
- [ ] Commit: `feat(accounting): seed Tekdüzen chart of accounts, journals, and VAT rates on tenant provisioning`

### Task 3: JournalEntryService çekirdeği — dengeli kayıt yazıcı + denge doğrulaması

**Dosyalar:**
- Create: `Modules/Accounting/app/Services/JournalEntryService.php`
- Test: `tests/Feature/Accounting/JournalEntryServiceTest.php`

**Interfaces:** `AccountingDefaultsService::accountByCode(int $tenantId, string $code): ChartOfAccount` (Task 2). Bu görev yalnızca ÇEKİRDEK yazma mekanizmasını kurar; `postForPurchaseReceipt`/`postForSalesDelivery`/`postForInvoice`/`postForPayment` metodları Task 4-8'de eklenecek (bu görevde yalnızca `write()` private helper'ı ve onu doğrudan test eden bir PUBLIC test-amaçlı metod YOKTUR — bunun yerine `write()` `protected` yapılıp test, `JournalEntryService`'i extend eden anonim bir test sınıfı YERİNE, PHPUnit'in reflection'ı ile DEĞİL, doğrudan gerçek bir iş akışı üzerinden test edilecek: Task 3'ün testi `write()`'ı Task 4'ün henüz yazılmadığı bu aşamada izole test etmek için `write()`'ı GEÇİCİ OLARAK public yapabilir VEYA — daha temiz — bu görev `write()`'ı hemen `postForPurchaseReceipt`'in ilk, en basit haliyle birlikte yazar. Aşağıdaki karar: **Task 3, `write()` + `postForPurchaseReceipt()`'i BİRLİKTE yazar** (write()'ı izole test etmenin en doğal yolu zaten bir posting metodu üzerinden olduğundan, Task 4 ile bu görev fiilen birleşir — bkz. Task 4'ün brief'i, Task 3 yalnızca `write()`'ın iskeletini ve denge testini kurar, Task 4 gerçek event/listener entegrasyonunu ekler).

```php
<?php

namespace Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        return \Illuminate\Support\Facades\DB::transaction(function () use ($tenantId, $journal, $entryDate, $reference, $lines): JournalEntry {
            $entry = new JournalEntry(['journal_id' => $journal->id, 'entry_date' => $entryDate, 'status' => 'posted']);
            $entry->tenant_id = $tenantId;
            $entry->reference()->associate($reference);
            $entry->save();

            foreach ($lines as $line) {
                $entryLine = new \Modules\Accounting\Models\JournalEntryLine([
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
}
```

- [ ] `JournalEntryService::write()` yaz (yukarıdaki kod)
- [ ] `JournalEntryServiceTest`: dengeli satırlarla `write()` çağrısı `journal_entries` + doğru sayıda `journal_entry_lines` üretir, `status='posted'`; DENGESİZ satırlarla (debit≠credit) çağrı 422 verir VE hiçbir `journal_entries`/`journal_entry_lines` kaydı OLUŞMAZ (transaction rollback — `assertDatabaseCount` ile doğrula); `reference()` doğru polymorphic modele bağlanıyor (test için mevcut herhangi bir model, ör. bir `Product` veya `StockMove` kullanılabilir — gerçek iş kuralı Task 4'te)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=JournalEntryServiceTest`
- [ ] Commit: `feat(accounting): balanced double-entry journal writer with 422 guard`

### Task 4: Satınalma teslim alımı muhasebeleşmesi (event + listener + postForPurchaseReceipt)

**Dosyalar:**
- Create: `Modules/Purchase/app/Events/PurchaseOrderLineReceived.php`
- Create: `Modules/Accounting/app/Listeners/CreateJournalEntryFromPurchaseReceipt.php`
- Modify: `Modules/Accounting/app/Services/JournalEntryService.php` (`postForPurchaseReceipt` eklenir), `Modules/Purchase/app/Services/PurchaseOrderService.php` (`receive()` sonunda event fırlatılır), `Modules/Accounting/app/Providers/EventServiceProvider.php`
- Test: `tests/Feature/Accounting/PurchaseReceiptAccountingTest.php`

**Interfaces:** `PurchaseOrderService::receive(PurchaseOrderLine $line, string $qty, int $receivingLocationId): void` (Faz 7, DEĞİŞMEYEN imza — yalnızca gövdesine event fırlatma satırı eklenir). `App\Models\Tenant::accounting_mode` (`anglo_saxon`/`continental`, `Modules/Purchase`'dan erişim: `Tenant::withoutGlobalScopes()->find($tenantId)`).

`Modules/Purchase/app/Events/PurchaseOrderLineReceived.php`:

```php
<?php

namespace Modules\Purchase\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Inventory\Models\StockMove;
use Modules\Purchase\Models\PurchaseOrderLine;

class PurchaseOrderLineReceived
{
    use Dispatchable;

    public function __construct(
        public PurchaseOrderLine $line,
        public StockMove $move,
    ) {}
}
```

`PurchaseOrderService::receive()`'in SONUNA (mevcut putaway transfer bloğundan SONRA, metod bitmeden önce) eklenecek satır:

```php
        PurchaseOrderLineReceived::dispatch($line, $move);
```
(`use Modules\Purchase\Events\PurchaseOrderLineReceived;` importu eklenir. `$move` zaten metodun başında `$this->stockMoves->move(...)`'dan dönen değişken — DEĞİŞTİRME, yalnızca event'e geçir.)

`JournalEntryService`'e eklenecek metod:

```php
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
```
(İmportlar: `Modules\Inventory\Models\Product`, `Modules\Inventory\Models\ProductCategory`, `Modules\Inventory\Models\StockMove`, `Modules\Purchase\Models\PurchaseOrderLine`, `App\Models\Tenant`. `now()->toDateString()` — bu proje test'lerinde `Carbon`/`now()` başka yerlerde de serbestçe kullanılıyor, tarih alanı için sorun değil, yalnızca Workflow script'lerinde yasak, bu normal bir Laravel servisidir.)

`Modules/Accounting/app/Listeners/CreateJournalEntryFromPurchaseReceipt.php`:

```php
<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Services\JournalEntryService;
use Modules\Purchase\Events\PurchaseOrderLineReceived;

class CreateJournalEntryFromPurchaseReceipt
{
    public function __construct(private readonly JournalEntryService $journalEntries) {}

    public function handle(PurchaseOrderLineReceived $event): void
    {
        $this->journalEntries->postForPurchaseReceipt($event->line, $event->move);
    }
}
```

`Modules/Accounting/app/Providers/EventServiceProvider.php` — mevcut dosyayı AÇ (Faz 0'dan kalma default nwidart iskeleti), `$listen` dizisine ekle:

```php
    protected $listen = [
        \Modules\Purchase\Events\PurchaseOrderLineReceived::class => [
            \Modules\Accounting\Listeners\CreateJournalEntryFromPurchaseReceipt::class,
        ],
    ];
```
(`Modules/Purchase/app/Providers/EventServiceProvider.php`'deki AYNI desen — `protected static $shouldDiscoverEvents = true;` zaten olabilir, DOKUNMA, yalnızca `$listen` dizisini doldur.)

- [ ] Event + listener + `postForPurchaseReceipt` + `PurchaseOrderService::receive()` değişikliği + `EventServiceProvider` kaydı
- [ ] `PurchaseReceiptAccountingTest`: (a) `product_category_id` atanmış, hesapları dolu bir ürünle teslim alım → doğru dr/cr (anglo-saxon: dr 153, continental: dr 621) / cr 320, tutar doğru; (b) `product_category_id` NULL bir ürünle teslim alım → HİÇBİR journal_entry OLUŞMAZ, `receive()` HATA VERMEDEN normal çalışır (regresyon: mevcut `PurchaseOrderReceiptTest` senaryosu — categorisiz ürün — HÂLÂ geçmeli, bunu da çalıştırıp doğrula); (c) accounting_mode='continental' olan bir tenant'ta doğru hesap (621) kullanılıyor
- [ ] `php artisan test --compact --filter=Purchase` (regresyon — Faz 7 testleri kırılmamalı) + `php artisan test --compact --filter=PurchaseReceiptAccountingTest`
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): post journal entry on purchase order receipt`

### Task 5: Satış teslimatı muhasebeleşmesi (event + listener + postForSalesDelivery, COGS event'e taşınır)

**Dosyalar:**
- Create: `Modules/Sales/app/Events/SalesOrderLineDelivered.php`
- Create: `Modules/Accounting/app/Listeners/CreateJournalEntryFromSalesDelivery.php`
- Modify: `Modules/Accounting/app/Services/JournalEntryService.php` (`postForSalesDelivery` eklenir), `Modules/Sales/app/Services/SalesOrderService.php` (`deliver()` — `consumeOutbound()`'un dönüş değeri artık YAKALANIR ve event'e taşınır), `Modules/Accounting/app/Providers/EventServiceProvider.php`
- Test: `tests/Feature/Accounting/SalesDeliveryAccountingTest.php`

**ÖNEMLİ:** `Modules/Sales/app/Services/SalesOrderService.php::deliver()` şu an `$this->costing->consumeOutbound($product, $move, $qty)` çağrısının dönüş değerini (COGS tutarı) HİÇ SAKLAMIYOR (Faz 8'de yazıldığında bu değere ihtiyaç yoktu). Bu görevde iki çağrı da (normal satır VE kit bileşeni satırları) dönüş değerini bir değişkende tutup event'e ekleyecek şekilde güncellenir — servisin GERİ KALAN mantığı (rezervasyon serbest bırakma, delivered_qty, transaction sarmalama) DEĞİŞMEZ, yalnızca `consumeOutbound()` çağrılarının SONUCU artık kullanılıyor.

`Modules/Sales/app/Events/SalesOrderLineDelivered.php`:

```php
<?php

namespace Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Inventory\Models\StockMove;
use Modules\Sales\Models\SalesOrderLine;

class SalesOrderLineDelivered
{
    use Dispatchable;

    public function __construct(
        public SalesOrderLine $line,
        public StockMove $move,
        public string $cogsAmount,
    ) {}
}
```

`SalesOrderService::deliver()` içindeki İKİ `consumeOutbound()` çağrı noktasında (kit bileşenleri döngüsü VE normal satır) değişiklik:

```php
            foreach ($moves as $move) {
                $moveProduct = Product::withoutGlobalScopes()->findOrFail($move->product_id);
                $cogs = $this->costing->consumeOutbound($moveProduct, $move, bcmul($move->qty, '-1', 4));
                SalesOrderLineDelivered::dispatch($line, $move, $cogs);
            }
```
ve normal satır dalında:
```php
        $cogs = $this->costing->consumeOutbound($product, $move, $qty);
        SalesOrderLineDelivered::dispatch($line, $move, $cogs);
```
(`use Modules\Sales\Events\SalesOrderLineDelivered;` eklenir. Kit dalında `$line` kit satırının kendisi — bileşen bazında AYRI bir `SalesOrderLine` yok, event'e KİT SATIRI geçirilir, `$move`/`$cogs` bileşene özgüdür; bu, `JournalEntryService::postForSalesDelivery`'nin ürün kategorisini `$move->product_id`'den (KİT satırının `$line->product_id`'sinden DEĞİL) çözmesini gerektirir — aşağıdaki kodda bu netleştirilmiştir.)

`JournalEntryService`'e eklenecek metod:

```php
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
```
(İmport: `Modules\Sales\Models\SalesOrderLine`.)

`Modules/Accounting/app/Listeners/CreateJournalEntryFromSalesDelivery.php`:

```php
<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Services\JournalEntryService;
use Modules\Sales\Events\SalesOrderLineDelivered;

class CreateJournalEntryFromSalesDelivery
{
    public function __construct(private readonly JournalEntryService $journalEntries) {}

    public function handle(SalesOrderLineDelivered $event): void
    {
        $this->journalEntries->postForSalesDelivery($event->line, $event->move, $event->cogsAmount);
    }
}
```

`Modules/Accounting/app/Providers/EventServiceProvider.php`'deki `$listen` dizisine EKLE (Task 4'teki satırın yanına, üzerine YAZMA):

```php
        \Modules\Sales\Events\SalesOrderLineDelivered::class => [
            \Modules\Accounting\Listeners\CreateJournalEntryFromSalesDelivery::class,
        ],
```

- [ ] Event + listener + `postForSalesDelivery` + `SalesOrderService::deliver()` değişikliği (iki çağrı noktası) + `EventServiceProvider` kaydı
- [ ] `SalesDeliveryAccountingTest`: (a) anglo_saxon tenant'ta, kategorisi tanımlı bir üründe normal satır teslimatı → dr 621 / cr 153, tutar = FIFO'dan hesaplanan gerçek COGS (test: 2 farklı maliyetli FIFO katmanından tüketim, COGS'un doğru toplandığını doğrula — `tests/Feature/Sales/SalesOrderDeliveryTest.php`'deki FIFO senaryosunu referans al); (b) continental tenant'ta AYNI teslimat → HİÇBİR journal_entry oluşmaz (`assertDatabaseCount('journal_entries', 0)`); (c) kit satırı teslimatında HER bileşen için AYRI bir journal_entry oluşur (bileşenin KENDİ kategorisi kullanılır, kit ürününün DEĞİL); (d) kategorisiz üründe teslimat sorunsuz çalışır, journal_entry oluşmaz (regresyon)
- [ ] `php artisan test --compact --filter=Sales` (regresyon — Faz 8 testleri kırılmamalı) + `php artisan test --compact --filter=SalesDeliveryAccountingTest`
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): post journal entry on sales delivery (anglo-saxon COGS recognition)`

### Task 6: InvoiceService — oluşturma + satır ekleme + 3 yönlü eşleştirme

**Dosyalar:**
- Create: `Modules/Accounting/app/Services/InvoiceService.php`
- Test: `tests/Feature/Accounting/InvoiceServiceTest.php`

**Interfaces:** `PurchaseOrderLine::receivedQty(): string` (Faz 7). `Invoice::subtotal()/taxTotal()/total()` (Task 1).

```php
<?php

namespace Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;

/**
 * Fatura oluşturma (PRD 3.10/3.11). 3 yönlü eşleştirme (PRD YENİ KURAL)
 * yalnızca type=purchase + source=PurchaseOrder + bill_control_policy=
 * received_qty iken uygulanır: faturalanan kümülatif miktar receivedQty()'yi
 * aşamaz. ordered_qty policy'sinde tavan purchase_order_lines.qty'dir
 * (teslimattan bağımsız — PRD: "PO onaylanır onaylanmaz fatura oluşturulabilir").
 */
class InvoiceService
{
    public function create(int $tenantId, int $partnerId, string $type, Model $source): Invoice
    {
        $invoice = new Invoice(['partner_id' => $partnerId, 'type' => $type, 'status' => 'draft']);
        $invoice->tenant_id = $tenantId;
        $invoice->source()->associate($source);
        $invoice->save();

        return $invoice;
    }

    public function addLine(Invoice $invoice, int $productId, string $qty, string $unitPrice, ?int $taxRateId): InvoiceLine
    {
        abort_unless($invoice->status === 'draft', 422, __('Lines can only be added to a draft invoice.'));

        if ($invoice->type === 'purchase' && $invoice->source instanceof PurchaseOrder) {
            $this->assertThreeWayMatch($invoice->source, $productId, $qty, $invoice);
        }

        $line = new InvoiceLine([
            'invoice_id' => $invoice->id,
            'product_id' => $productId,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'tax_rate_id' => $taxRateId,
        ]);
        $line->tenant_id = $invoice->tenant_id;
        $line->save();

        return $line;
    }

    private function assertThreeWayMatch(PurchaseOrder $po, int $productId, string $newQty, Invoice $invoice): void
    {
        $poLine = PurchaseOrderLine::withoutGlobalScopes()
            ->where('purchase_order_id', $po->id)->where('product_id', $productId)->first();

        abort_if($poLine === null, 422, __('This product is not on the purchase order.'));

        $alreadyInvoiced = InvoiceLine::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->whereIn('invoice_id', Invoice::withoutGlobalScopes()
                ->where('source_type', 'purchase_order')->where('source_id', $po->id)->pluck('id'))
            ->sum('qty');

        $cumulative = bcadd((string) $alreadyInvoiced, $newQty, 4);

        $cap = $po->bill_control_policy === 'received_qty'
            ? $poLine->receivedQty()
            : $poLine->qty;

        abort_if(bccomp($cumulative, $cap, 4) > 0, 422, __('Invoiced quantity cannot exceed the allowed quantity for this purchase order line.'));
    }
}
```

- [ ] `InvoiceService` yaz (yukarıdaki kod)
- [ ] `InvoiceServiceTest`: satış faturası oluşturma + satır ekleme (3 yönlü eşleştirme YOK, herhangi bir miktar kabul edilir); satınalma faturası, `bill_control_policy='received_qty'`, teslim alınandan FAZLA faturalama → 422; TAM teslim alınan kadar → başarılı; `bill_control_policy='ordered_qty'`, teslim alınmadan (receivedQty=0) TAM sipariş miktarı kadar faturalama → başarılı (teslimattan bağımsız); AYNI PO'ya iki AYRI fatura ile KÜMÜLATİF aşım → ikinci fatura 422
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=InvoiceServiceTest`
- [ ] Commit: `feat(accounting): invoice creation with three-way matching for purchase invoices`

### Task 7: Fatura onayı (posting) — KDV/gelir muhasebeleşmesi

**Dosyalar:**
- Modify: `Modules/Accounting/app/Services/InvoiceService.php` (`post()` eklenir), `Modules/Accounting/app/Services/JournalEntryService.php` (`postForInvoice` eklenir)
- Test: `tests/Feature/Accounting/InvoicePostingTest.php`

`InvoiceService`'e eklenecek:

```php
    public function __construct(private readonly JournalEntryService $journalEntries) {}

    public function post(Invoice $invoice, User $poster): void
    {
        abort_unless($invoice->status === 'draft', 422, __('Only a draft invoice can be posted.'));
        abort_unless($poster->can('post journal entries'), 403, __('You are not allowed to post journal entries.'));

        $this->journalEntries->postForInvoice($invoice);

        $invoice->update(['status' => 'posted']);
    }
```
(`use App\Models\User;` eklenir; constructor'a `JournalEntryService` inject edilir — `create()`/`addLine()` metodları DEĞİŞMEZ.)

`JournalEntryService`'e eklenecek:

```php
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
        $subtotal = $invoice->subtotal();
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
```
**NOT (basitleştirme — birden fazla satırlı satış faturasında satır bazında gelir hesabı toplamı `$subtotal`'a eşit olmalı):** yukarıdaki döngü her satırın KENDİ `income_account_id`'sini kullanır (farklı ürün kategorileri farklı gelir hesaplarına sahip olabilir) — bu, tek bir `$subtotal` satırı yerine ÇOKLU credit satırı üretir, toplamları yine de `subtotal()`'a eşittir (denge korunur). Satınalma faturasında bu karmaşıklık YOKTUR çünkü yalnızca KDV satırı eklenir (mal değeri zaten Task 4'te kaydedildi).

- [ ] `InvoiceService::post()` + `JournalEntryService::postForInvoice()` yaz
- [ ] `InvoicePostingTest`: satınalma faturası onayı → dr 191 / cr 320, tutar = yalnızca KDV; satış faturası onayı (tek satır) → dr 120(brüt) / cr income(net) / cr 391(KDV), 3 satır dengeli; satış faturası (2 FARKLI kategoriden 2 satır) → 4 satırlı dengeli kayıt (120 + 2 farklı income hesabı + 391); draft olmayan faturayı tekrar post etme → 422; `post journal entries` izni olmayan kullanıcı → 403; satınalma faturasında KDV tutarı sıfırsa (tax_rate_id hiç verilmemiş) → 422 ("faturalanacak KDV yok" — bilinçli kısıtlama, dokümante edilir)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=InvoicePostingTest`
- [ ] Commit: `feat(accounting): post invoice journal entries (VAT for purchase, revenue recognition for sale)`

### Task 8: PaymentService — tahsilat/ödeme + kısmi dağıtım + otomatik paid geçişi

**Dosyalar:**
- Create: `Modules/Accounting/app/Services/PaymentService.php`
- Modify: `Modules/Accounting/app/Services/JournalEntryService.php` (`postForPayment` eklenir)
- Test: `tests/Feature/Accounting/PaymentServiceTest.php`

**Interfaces:** `Invoice::remainingBalance(): string`, `Payment::unallocatedAmount(): string` (Task 1).

```php
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
    public function __construct(private readonly JournalEntryService $journalEntries) {}

    public function create(int $tenantId, int $partnerId, int $journalId, string $amount, string $paymentDate): Payment
    {
        $journal = Journal::withoutGlobalScopes()->findOrFail($journalId);
        abort_unless(in_array($journal->type, ['cash', 'bank'], true), 422, __('Payments must use a cash or bank journal.'));

        $payment = new Payment([
            'partner_id' => $partnerId,
            'journal_id' => $journalId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
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
```

`JournalEntryService`'e eklenecek:

```php
    /**
     * Ödeme/tahsilat kaydı (PRD 3.12): tedarikçiye ödeme → dr Satıcılar(320)
     * / cr kasa-banka; müşteriden tahsilat → dr kasa-banka / cr Alıcılar(120).
     * Kasa/banka hesabı journal'ın type'ına göre kod üzerinden çözülür
     * (cash→100, bank→102).
     */
    public function postForPayment(Payment $payment, Invoice $invoice, string $amount): JournalEntry
    {
        $cashOrBankCode = $payment->journal->type === 'cash' ? '100' : '102';
        $cashOrBank = $this->defaults->accountByCode($payment->tenant_id, $cashOrBankCode);
        $controlAccount = $this->defaults->accountByCode($payment->tenant_id, $invoice->type === 'purchase' ? '320' : '120');

        $lines = $invoice->type === 'purchase'
            ? [
                ['account_id' => $controlAccount->id, 'debit' => $amount, 'credit' => '0.0000'],
                ['account_id' => $cashOrBank->id, 'debit' => '0.0000', 'credit' => $amount],
            ]
            : [
                ['account_id' => $cashOrBank->id, 'debit' => $amount, 'credit' => '0.0000'],
                ['account_id' => $controlAccount->id, 'debit' => '0.0000', 'credit' => $amount],
            ];

        return $this->write(
            tenantId: $payment->tenant_id,
            journalType: $payment->journal->type,
            entryDate: $payment->payment_date->toDateString(),
            reference: $payment,
            lines: $lines,
        );
    }
```

- [ ] `PaymentService` + `JournalEntryService::postForPayment()` yaz
- [ ] `PaymentServiceTest`: tedarikçiye TAM ödeme (fatura brüt tutarı kadar) → dr 320 / cr 100 veya 102, fatura `status='paid'`; müşteriden KISMİ tahsilat (fatura tutarının yarısı) → dr 100/102 / cr 120, fatura status HÂLÂ `posted` (paid DEĞİL), `remainingBalance()` doğru; AYNI ödemenin İKİNCİ bir faturaya kalan tutarla dağıtılması (tek ödeme, iki fatura) → her ikisi de doğru güncelleniyor; ödemenin kalan tutarını AŞAN bir dağıtım denemesi → 422; faturanın kalan bakiyesini AŞAN bir dağıtım denemesi → 422; `cash`/`bank` DIŞINDA bir journal ile ödeme oluşturma denemesi → 422
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=PaymentServiceTest`
- [ ] Commit: `feat(accounting): payment allocation with partial support and auto-paid transition`

### Task 9: Uçtan uca kabul kriteri testi — alım/satış zinciri + mizan doğrulaması

**Dosyalar:**
- Test: `tests/Feature/Accounting/AccountingEndToEndTest.php`
- Yeni servis kodu YOK.

Bu görev PRD'nin Faz 9 kabul kriterini birebir kanıtlar: "Alım→teslim→fatura→ödeme zinciri baştan sona doğru yevmiye seti üretir; mizan dengede." TEK bir test dosyasında İKİ senaryo:

**`test_purchase_to_payment_chain_produces_a_balanced_journal_set`**: (anglo_saxon tenant) PO oluştur (partner=tedarikçi, ürün kategorisi hesapları tanımlı) → confirm (farklı kullanıcı) → receive (10 adet @ 100 = 1000 net) → dr 153/cr 320 kaydı doğrula → `InvoiceService` ile fatura oluştur+satır ekle (10 adet @ 100, KDV %20 alış) → post → dr 191(200)/cr 320(200) doğrula → `PaymentService` ile 1200 (1000+200) tam ödeme → dr 320(1200)/cr 100 veya 102(1200) doğrula, fatura `paid`. SONUNDA: `JournalEntryLine::where('account_id', $payables320->id)->sum(...)` ile 320 hesabının net bakiyesinin (`debit toplamı - credit toplamı` ya da tersi, kodda `sum('debit')-sum('credit')` şeklinde) SIFIR olduğunu doğrula (borç tamamen kapandı).

**`test_sales_to_receipt_chain_produces_a_balanced_journal_set`**: (aynı anglo_saxon tenant, aynı ürün — Task 9'daki ilk senaryodan kalan stok/FIFO katmanı kullanılabilir VEYA bağımsız yeni bir ürün/stok kurulur, implementer karar verir) SO oluştur (partner=müşteri, location) → confirm (rezerve) → deliver (4 adet, COGS FIFO'dan hesaplanır) → dr 621/cr 153 doğrula → fatura oluştur+satır ekle (4 adet @ satış fiyatı, KDV %20 satış) → post → dr 120(brüt)/cr 600(net)/cr 391(KDV) doğrula → tam tahsilat → dr 100 veya 102/cr 120, fatura `paid`. SONUNDA: 120 hesabının net bakiyesinin SIFIR olduğunu doğrula.

**Genel mizan (trial balance) doğrulaması**: her iki senaryo sonunda, TÜM `journal_entry_lines` satırlarının `SUM(debit)` toplamının `SUM(credit)` toplamına EŞİT olduğunu (`assertSame` ile, bcmath karşılaştırması) doğrulayan tek bir assertion — bu, "mizan dengede" kabul kriterinin doğrudan kanıtıdır (her tekil kaydın zaten dengeli olması matematiksel olarak toplamın da dengeli olacağını garanti eder, ama kabul kriterinin AÇIKÇA test edilmesi için ayrı bir assertion yazılır).

- [ ] `AccountingEndToEndTest` yaz (yukarıdaki iki senaryo + mizan assertion'ı)
- [ ] `php artisan test --compact --filter=Accounting` (TÜM Faz 9 testleri)
- [ ] Tüm suite: `php artisan test --compact`
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Hafıza güncellemesi: `erp-ilerleme-durumu.md`'ye Faz 9 özeti (hesap kodu sözleşmesi kararı, receipt/invoice ayrımının neden çifte saymadığı, continental modda satışta neden kayıt olmadığı, best-effort/sessiz atlama davranışı dahil)
- [ ] Commit: `feat(accounting): end-to-end purchase-to-payment and sales-to-receipt acceptance tests`

## Self-Review
- PRD 3.12'deki tüm maddeler eşlendi: hesap planı+journal seed (Task 2), otomatik kayıt üretimi (Task 4/5/7/8), KDV (Task 6/7), tahsilat/ödeme kısmi dağıtım (Task 8), çift taraflı denge zorunluluğu (Task 3).
- PRD'nin ham şeması yalnızca 4 ürün-kategorisi hesabı + `tax_rates.tax_account_id` verdiğinden, Alıcılar(120)/Satıcılar(320)/Kasa(100)/Banka(102) için KOD ÜZERİNDEN sözleşmeli erişim BİLİNÇLİ bir tasarım kararıdır (Architecture madde 1) — Tekdüzen Hesap Planı'nın ulusal standart olması bunu meşrulaştırır.
- Receipt'te net değer + invoice'da yalnızca KDV ayrımı, çifte sayımı YAPISAL olarak imkansız kılar (aynı hesaba iki kez tam değer yazılmaz) — bu, PRD'nin ayrı ayrı "her stock_moves" VE "her invoices" cümlelerini ÇELİŞKİSİZ birleştiren TEK yorumdur.
- Continental modda satış teslimatında kayıt ÜRETİLMEMESİ bilinçli bir sadeleştirme, PRD'nin "Anglo-Saxon'da COGS satışta / Continental'da girişte" ayrımının doğrudan ve tek tutarlı sonucu.
- Dönem sonu stok değeri düzeltmesi (continental mod için) bu fazın KAPSAMI DIŞINDA — PRD de bunu ayrıntılandırmıyor, yalnızca kavramsal olarak anıyor.
- Otomatik muhasebe kaydının "best-effort" (kategori/hesap eksikse sessizce atlanması) davranışı, hem gerçek iş kuralı (Muhasebe her tenant'ta aktif/yapılandırılmış olmayabilir) hem de mevcut Faz 7/8 testlerinin regresyonsuz kalması için ZORUNLUdur — Task 4/5'in testleri bunu açıkça doğrular.
