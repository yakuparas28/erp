# Faz 10: Çoklu Para Birimi + Türkiye Uyumu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development.

**Goal:** PRD 3.13/3.14/4.1.5 — fatura/ödemenin TL dışında bir para biriminde düzenlenebilmesi, işlem tarihindeki TCMB ALIŞ kurunun belgeye kilitlenmesi, tahsilat/ödeme kuru fatura kurundan farklıysa gerçekleşen kambiyo karı/zararının (646/656) otomatik muhasebeleşmesi, dönem sonu açık döviz bakiyeleri için gerçekleşmemiş değerleme, ve e-Fatura/e-Arşiv durum makinesi (Strategy pattern). Kabul kriteri: USD fatura (kur 30) + tahsilat (kur 32) → (32-30)×tutar kambiyo karı 646'da otomatik kaydedilir.

**Architecture — kilit mimari kararlar:**

1. **Fonksiyonel para birimi (TL) kavramı `currency_id = NULL` ile temsil edilir** — `invoices`/`payments` tablolarına eklenen `currency_id`/`exchange_rate_used` NULL ise belge TL'dedir, `exchange_rate_used` etkin olarak `1` kabul edilir (`Invoice::exchangeRateOrOne()`/`Payment::exchangeRateOrOne()` helper'ları). `currencies` tablosunda TRY (`is_functional=true`) yine de seed edilir (tutarlılık/gelecekteki ekranlar için) ama hiçbir belge TL için `currency_id` set ETMEZ.
2. **Kur, belgenin KENDİ tarihinde (fatura: oluşturma anı `now()`; ödeme: `payment_date` alanı) TCMB ALIŞ kuruyla KİLİTLENİR ve BİR DAHA ASLA DEĞİŞMEZ** — `InvoiceService::create()`/`PaymentService::create()`'e eklenen opsiyonel `?int $currencyId = null` parametresi, verildiğinde `ExchangeRateService::lockRateFor()` ile o günün `exchange_rates.buy_rate`'ini okuyup `exchange_rate_used`'a yazar. Kur bulunamazsa 422 (kullanıcı önce `recordManualRate()` ile elle girmeli).
3. **Kapsam daraltması (bilinçli): Yalnızca `invoices` ve `payments` çoklu para birimi taşır — `purchase_orders`/`sales_orders`'a `currency_id` EKLENMEZ bu fazda.** PRD'nin şeması bu iki tabloyu da listeler ama fazın kabul kriteri ve tüm test senaryoları yalnızca fatura+ödeme zincirini kapsıyor; PO/SO'ya kullanılmayan/spekülatif kolon eklemek "yarım kalan implementasyon" ilkesine aykırı olurdu. `InvoiceService::create()`'e verilen `currencyId`, kaynağı olan PO/SO'nun para biriminden BAĞIMSIZDIR (PO/SO örtük olarak hep TL'dir, fatura ayrı bir para biriminde düzenlenebilir — bu, döviz faturalı ama TL bazlı iç sipariş takibi yapan gerçek Türkiye SME pratiğiyle tutarlıdır).
4. **Tek para birimi kısıtı: bir `Payment` yalnızca AYNI para biriminde bir faturaya dağıtılabilir** — `PaymentService::allocate()` `abort_if($payment->currency_id !== $invoice->currency_id, 422, ...)`. Farklı para birimleri arası dağıtım (ör. USD ödeme → TL fatura) bu fazın kapsamı DIŞINDA (dokümante edilmiş sadeleştirme).
5. **Gerçekleşen kambiyo farkı, `JournalEntryService::postForPayment()`'ın 2 satırlık kaydını 3 SATIRA genişletir** (yalnızca kurlar farklıysa): kontrol hesabı (120/320) faturanın KENDİ kilitli kuruyla (orijinal defter değerini kapatacak TL tutarı), kasa/banka ödemenin KENDİ kilitli kuruyla (gerçek nakit hareketi), aradaki fark 646 (kâr) veya 656 (zarar) — yön faturanın `type`'ına göre ters işaretlidir (satışta kur artışı kâr, alışta kur artışı zarar). Bu üç satır HER ZAMAN dengelidir (matematiksel olarak: cash_TL = control_TL ± fark).
6. **Dönem sonu gerçekleşmemiş değerleme (`FxRevaluationService::revaluateOpenBalances()`) `invoice.exchange_rate_used`'u DEĞİŞTİRMEZ** — yalnızca raporlama amaçlı bir `fx_revaluations(type=unrealized)` kaydı + 120/320↔646/656 arası bir muhasebe kaydı üretir. Bilinçli sadeleştirme: gerçek muhasebe pratiğinde bu kayıt bir sonraki dönem başında TERS KAYITLA (reversal) geri alınır ki aynı kur hareketi daha sonra gerçekleşen kayıtla ÇİFTE SAYILMASIN — bu reversal mekanizması PRD'de detaylandırılmadığından ve gerçek karmaşıklığı yüksek olduğundan bu fazın kapsamı DIŞINDA bırakılmıştır (dokümante edilmiş, kabul edilebilir bir sadeleştirme — Faz 6'daki "closest≈fifo" ve Faz 9'daki "continental dönem sonu stok düzeltmesi" sadeleştirmeleriyle AYNI kategoride).
7. **e-Fatura durum makinesi bağımsızdır, muhasebe kaydını ETKİLEMEZ** — `EInvoiceProviderInterface` (Strategy, `CostingStrategyInterface`'teki desenle tutarlı) yalnızca `invoices.e_invoice_status` (not_sent→sent→accepted/rejected) ve `gib_uuid`'i yönetir; `JournalEntryService`'e hiçbir etkisi yoktur (fatura muhasebe kaydı `InvoiceService::post()`'ta zaten oluşur, e-Fatura gönderimi AYRI ve SONRAKI bir adımdır).

## Global Constraints
- Master plan + Faz 9 kısıtları geçerli (bcmath her yerde float YOK; FK yok; `DB::raw`/trigger yasak).
- Mevcut `InvoiceService::create()`/`PaymentService::create()` çağrıları (Faz 9'un TÜM testleri) YENİ `?int $currencyId = null` parametresi OLMADAN çağrılmaya devam eder — bu parametre trailing/opsiyonel olduğundan GERİYE DÖNÜK UYUMLU olmalı, mevcut hiçbir test BOZULMAMALI.
- `JournalEntryService::postForPayment()`'ın imza değişikliği (varsa) da aynı şekilde geriye dönük uyumlu olmalı.
- Bu faz EKRAN İÇERMİYOR (Faz 5/6/7/9-backend gibi yalnızca motor+model+test).
- Yeni metinler `__('EN')` + `lang/tr.json` (bu faz ekran içermediğinden kullanıcıya gösterilen tek metinler hata mesajlarıdır, yine de eklenmeli).
- `vendor/bin/pint --dirty --format agent` her PHP değişikliğinden sonra.

---

### Task 1: Migrasyonlar + modeller + factory'ler + Currency seed'i

**Dosyalar:**
- Create: `Modules/Accounting/database/migrations/2026_07_23_800001_create_currencies_table.php`, `..._800002_create_exchange_rates_table.php`, `..._800003_create_fx_revaluations_table.php`, `..._800004_add_currency_fields_to_invoices_and_payments_table.php`, `..._800005_add_e_invoice_fields_to_invoices_table.php`
- Create: `Modules/Accounting/app/Models/Currency.php`, `ExchangeRate.php`, `FxRevaluation.php` + factory'ler
- Modify: `Modules/Accounting/app/Models/Invoice.php`, `Payment.php` (currency_id/exchange_rate_used fillable + `exchangeRateOrOne()` helper + `currency()` ilişkisi)
- Modify: `Modules/Accounting/app/Services/AccountingDefaultsService.php` (Currency seed'i eklenir — YENİ bir `CurrencyDefaultsService` AÇMAYA GEREK YOK, mevcut `provision()` metoduna eklenir çünkü zaten tenant provisioning'de TEK bir yerden çağrılıyor)
- Test: `tests/Feature/Accounting/CurrencyModelTest.php`

```php
// create_currencies_table.php
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->string('code'); // 'TRY', 'USD', 'EUR'
    $table->string('name');
    $table->boolean('is_functional')->default(false);
    $table->timestamps();

    $table->unique(['tenant_id', 'code']);
});
```

```php
// create_exchange_rates_table.php
Schema::create('exchange_rates', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->unsignedBigInteger('currency_id');
    $table->date('rate_date');
    $table->decimal('buy_rate', 15, 6);
    $table->decimal('sell_rate', 15, 6);
    $table->enum('source', ['tcmb', 'manual']);
    $table->timestamps();

    $table->unique(['tenant_id', 'currency_id', 'rate_date']);
});
```

```php
// create_fx_revaluations_table.php
Schema::create('fx_revaluations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id');
    $table->unsignedBigInteger('invoice_id');
    $table->unsignedBigInteger('payment_id')->nullable(); // yalnızca realized için
    $table->enum('type', ['realized', 'unrealized']);
    $table->decimal('difference_amount', 15, 4); // işaretli: + kâr, - zarar (646/656 yönü buradan türetilir)
    $table->date('revaluation_date');
    $table->timestamps();

    $table->index(['tenant_id', 'invoice_id']);
});
```

```php
// add_currency_fields_to_invoices_and_payments_table.php
Schema::table('invoices', function (Blueprint $table) {
    $table->unsignedBigInteger('currency_id')->nullable()->after('partner_id'); // null: TL (fonksiyonel)
    $table->decimal('exchange_rate_used', 15, 6)->nullable()->after('currency_id');
});

Schema::table('payments', function (Blueprint $table) {
    $table->unsignedBigInteger('currency_id')->nullable()->after('partner_id');
    $table->decimal('exchange_rate_used', 15, 6)->nullable()->after('currency_id');
});
```

```php
// add_e_invoice_fields_to_invoices_table.php
Schema::table('invoices', function (Blueprint $table) {
    $table->enum('e_invoice_type', ['e_fatura', 'e_arsiv', 'kagit'])->default('kagit');
    $table->enum('e_invoice_status', ['not_sent', 'sent', 'accepted', 'rejected'])->default('not_sent');
    $table->string('gib_uuid')->nullable();
});
```

Modeller (`Modules/Accounting/app/Models/PurchaseOrder.php` gibi mevcut model dosyalarındaki `BelongsToTenant, HasFactory` deseninde):

```php
// Currency.php
class Currency extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'code', 'name', 'is_functional'];

    protected function casts(): array
    {
        return ['is_functional' => 'boolean'];
    }

    protected static function newFactory(): CurrencyFactory { return CurrencyFactory::new(); }
}
```

```php
// ExchangeRate.php
class ExchangeRate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'currency_id', 'rate_date', 'buy_rate', 'sell_rate', 'source'];

    protected function casts(): array
    {
        return ['rate_date' => 'date', 'buy_rate' => 'decimal:6', 'sell_rate' => 'decimal:6'];
    }

    protected static function newFactory(): ExchangeRateFactory { return ExchangeRateFactory::new(); }

    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
}
```

```php
// FxRevaluation.php
class FxRevaluation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'invoice_id', 'payment_id', 'type', 'difference_amount', 'revaluation_date'];

    protected function casts(): array
    {
        return ['difference_amount' => 'decimal:4', 'revaluation_date' => 'date'];
    }

    protected static function newFactory(): FxRevaluationFactory { return FxRevaluationFactory::new(); }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
```

`Invoice.php`/`Payment.php`'ye eklenecek (her ikisine de aynı desen):

```php
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function exchangeRateOrOne(): string
    {
        return $this->exchange_rate_used ?? '1.000000';
    }
```
(`$fillable`'a `currency_id`/`exchange_rate_used` eklenir; `casts()`'e `'exchange_rate_used' => 'decimal:6'` eklenir.)

`AccountingDefaultsService::provision()`'a eklenecek (mevcut hesap/journal/tax_rate seed döngülerinin ardına):

```php
    private const CURRENCIES = [
        ['code' => 'TRY', 'name' => 'Türk Lirası', 'is_functional' => true],
        ['code' => 'USD', 'name' => 'ABD Doları', 'is_functional' => false],
        ['code' => 'EUR', 'name' => 'Euro', 'is_functional' => false],
    ];

    // provision() içine:
    foreach (self::CURRENCIES as $currency) {
        Currency::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $currency['code']],
            ['name' => $currency['name'], 'is_functional' => $currency['is_functional']],
        );
    }
```

- [ ] Migration'lar + modeller + factory'ler yaz; `php artisan migrate --no-interaction`
- [ ] `AccountingDefaultsService::provision()`'a Currency seed'i ekle
- [ ] `Invoice`/`Payment` modellerine `currency()`/`exchangeRateOrOne()` ekle
- [ ] `CurrencyModelTest`: `provision()` sonrası 3 currency oluşuyor (TRY `is_functional=true`, USD/EUR `false`); idempotent; `Invoice`/`Payment`'ta `currency_id` null iken `exchangeRateOrOne()` `'1.000000'` döner, dolu iken kendi değerini döner
- [ ] `php artisan test --compact --filter=Accounting` (regresyon — Faz 9 testleri kırılmamalı, yeni nullable kolonlar mevcut factory'leri etkilememeli)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): currency, exchange rate, and fx revaluation data model`

### Task 2: ExchangeRateService — kur kilitleme + TCMB senkronizasyonu + manuel giriş

**Dosyalar:**
- Create: `Modules/Accounting/app/Services/ExchangeRateService.php`
- Create: `Modules/Accounting/app/Contracts/TcmbClientInterface.php`
- Create: `Modules/Accounting/app/Services/Tcmb/HttpTcmbClient.php`
- Create: `Modules/Accounting/app/Console/Commands/SyncExchangeRates.php`
- Modify: `Modules/Accounting/app/Providers/AccountingServiceProvider.php` (interface binding), `routes/console.php` (scheduled command kaydı)
- Test: `tests/Feature/Accounting/ExchangeRateServiceTest.php`

```php
<?php

namespace Modules\Accounting\Contracts;

/**
 * TCMB kur servisiyle iletişim sözleşmesi (Strategy — CostingStrategyInterface
 * ile aynı desende). Gerçek implementasyon TCMB'nin günlük kur.xml'ini okur;
 * testlerde bu arayüz sahtelenir.
 */
interface TcmbClientInterface
{
    /**
     * @return array<string, array{buy: string, sell: string}> — currency code => rates
     */
    public function fetchRates(string $date): array;
}
```

```php
<?php

namespace Modules\Accounting\Services\Tcmb;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\Accounting\Contracts\TcmbClientInterface;

class HttpTcmbClient implements TcmbClientInterface
{
    private const TRACKED_CODES = ['USD', 'EUR'];

    public function fetchRates(string $date): array
    {
        $parsed = Carbon::parse($date);
        $url = "https://www.tcmb.gov.tr/kurlar/{$parsed->format('Ym')}/{$parsed->format('dmY')}.xml";

        $response = Http::get($url);

        abort_unless($response->successful(), 422, __('TCMB exchange rates could not be fetched for this date.'));

        $xml = simplexml_load_string($response->body());

        abort_if($xml === false, 422, __('TCMB exchange rate response could not be parsed.'));

        $rates = [];

        foreach ($xml->Currency as $currency) {
            $code = (string) $currency['CurrencyCode'];

            if (in_array($code, self::TRACKED_CODES, true)) {
                $rates[$code] = [
                    'buy' => (string) $currency->ForexBuying,
                    'sell' => (string) $currency->ForexSelling,
                ];
            }
        }

        return $rates;
    }
}
```

```php
<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Contracts\TcmbClientInterface;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;

/**
 * Kur kilitleme (PRD 3.13 — belgenin oluşturulduğu tarihteki TCMB ALIŞ
 * kuru) + günlük TCMB senkronizasyonu + istisnai günler için manuel giriş.
 */
class ExchangeRateService
{
    public function __construct(private readonly TcmbClientInterface $client) {}

    public function lockRateFor(int $tenantId, int $currencyId, string $date): string
    {
        $rate = ExchangeRate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('currency_id', $currencyId)
            ->where('rate_date', $date)
            ->first();

        abort_if($rate === null, 422, __('No exchange rate is available for this currency on this date.'));

        return (string) $rate->buy_rate;
    }

    public function syncFromTcmb(int $tenantId, ?string $date = null): void
    {
        $date ??= now()->toDateString();
        $rates = $this->client->fetchRates($date);

        foreach ($rates as $code => $rate) {
            $currency = Currency::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('code', $code)->first();

            if ($currency === null) {
                continue;
            }

            ExchangeRate::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'currency_id' => $currency->id, 'rate_date' => $date],
                ['buy_rate' => $rate['buy'], 'sell_rate' => $rate['sell'], 'source' => 'tcmb'],
            );
        }
    }

    public function recordManualRate(int $tenantId, int $currencyId, string $date, string $buyRate, string $sellRate): ExchangeRate
    {
        return ExchangeRate::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'currency_id' => $currencyId, 'rate_date' => $date],
            ['buy_rate' => $buyRate, 'sell_rate' => $sellRate, 'source' => 'manual'],
        );
    }
}
```

`SyncExchangeRates` console komutu (tüm tenant'lar için döngü — `App\Models\Tenant::all()`):

```php
<?php

namespace Modules\Accounting\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Accounting\Services\ExchangeRateService;

class SyncExchangeRates extends Command
{
    protected $signature = 'accounting:sync-exchange-rates';

    protected $description = 'Sync daily TCMB exchange rates for all tenants';

    public function handle(ExchangeRateService $exchangeRates): int
    {
        foreach (Tenant::withoutGlobalScopes()->cursor() as $tenant) {
            $exchangeRates->syncFromTcmb($tenant->id);
        }

        return self::SUCCESS;
    }
}
```

`Modules/Accounting/app/Providers/AccountingServiceProvider.php`'nin `register()` metoduna ekle: `$this->app->bind(\Modules\Accounting\Contracts\TcmbClientInterface::class, \Modules\Accounting\Services\Tcmb\HttpTcmbClient::class);`

`routes/console.php`'ye ekle (dosyayı AÇIP mevcut `Schedule::` çağrılarının deseninde): `Schedule::command('accounting:sync-exchange-rates')->dailyAt('09:00');`

- [ ] `TcmbClientInterface` + `HttpTcmbClient` + `ExchangeRateService` + `SyncExchangeRates` komutu yaz
- [ ] `AccountingServiceProvider`'a binding ekle, `routes/console.php`'ye schedule ekle
- [ ] `ExchangeRateServiceTest`: `Http::fake()` ile sahte bir TCMB XML yanıtı (USD/EUR ForexBuying/ForexSelling) döndürüp `syncFromTcmb()`'in doğru `exchange_rates` satırlarını (`source='tcmb'`) oluşturduğunu doğrula; `lockRateFor()` mevcut bir kur için doğru `buy_rate`'i döner, olmayan tarih için 422; `recordManualRate()` `source='manual'` ile kayıt oluşturur/üzerine yazar (`updateOrCreate` — aynı gün ikinci kez çağrılırsa GÜNCELLENİR, çoğaltılmaz); komut (`php artisan accounting:sync-exchange-rates` `$this->artisan(...)` ile) birden fazla tenant için doğru çalışır (`Http::fake()` altında)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=ExchangeRateServiceTest`
- [ ] Commit: `feat(accounting): exchange rate locking, TCMB sync, and manual rate entry`

### Task 3: Fatura/Ödeme oluşturmada kur kilitleme + TL çevrimli muhasebe kaydı

**Dosyalar:**
- Modify: `Modules/Accounting/app/Services/InvoiceService.php` (`create()` imzası genişler), `Modules/Accounting/app/Services/PaymentService.php` (`create()` imzası genişler), `Modules/Accounting/app/Services/JournalEntryService.php` (`postForInvoice()` TL çevrimi kullanır)
- Test: `tests/Feature/Accounting/ForeignCurrencyInvoiceTest.php`

**Interfaces:** `ExchangeRateService::lockRateFor(int $tenantId, int $currencyId, string $date): string` (Task 2). `Invoice::exchangeRateOrOne(): string` (Task 1).

`InvoiceService::create()` — YENİ opsiyonel trailing parametre, mevcut çağrılar ETKİLENMEZ:

```php
    public function __construct(
        private readonly JournalEntryService $journalEntries,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function create(int $tenantId, int $partnerId, string $type, Model $source, ?int $currencyId = null): Invoice
    {
        $invoice = new Invoice([
            'partner_id' => $partnerId,
            'type' => $type,
            'status' => 'draft',
            'currency_id' => $currencyId,
            'exchange_rate_used' => $currencyId !== null
                ? $this->exchangeRates->lockRateFor($tenantId, $currencyId, now()->toDateString())
                : null,
        ]);
        $invoice->tenant_id = $tenantId;
        $invoice->source()->associate($source);
        $invoice->save();

        return $invoice;
    }
```

`PaymentService::create()` — AYNI desen, `$paymentDate`'i kullanarak (payment'ın KENDİ tarihi, `now()` DEĞİL):

```php
    public function __construct(
        private readonly JournalEntryService $journalEntries,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function create(int $tenantId, int $partnerId, int $journalId, string $amount, string $paymentDate, ?int $currencyId = null): Payment
    {
        $journal = Journal::withoutGlobalScopes()->findOrFail($journalId);
        abort_unless(in_array($journal->type, ['cash', 'bank'], true), 422, __('Payments must use a cash or bank journal.'));

        $payment = new Payment([
            'partner_id' => $partnerId,
            'journal_id' => $journalId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'currency_id' => $currencyId,
            'exchange_rate_used' => $currencyId !== null
                ? $this->exchangeRates->lockRateFor($tenantId, $currencyId, $paymentDate)
                : null,
        ]);
        $payment->tenant_id = $tenantId;
        $payment->save();

        return $payment;
    }
```

`JournalEntryService::postForInvoice()` — mevcut kodu AÇIP oku, `$tax`/`$total`/`$line->subtotal()` kullanılan yerlerde TL karşılığını kullan (`bcmul(değer, $invoice->exchangeRateOrOne(), 4)`). Satınalma dalı:

```php
        $taxTL = bcmul($tax, $invoice->exchangeRateOrOne(), 4);
        // ... lines içinde $tax yerine $taxTL kullanılır
```
Satış dalı:
```php
        $totalTL = bcmul($invoice->total(), $invoice->exchangeRateOrOne(), 4);
        // receivables satırı $total yerine $totalTL
        // her satırın income kısmı: bcmul($line->subtotal(), $invoice->exchangeRateOrOne(), 4)
        // KDV satırı: bcmul($tax, $invoice->exchangeRateOrOne(), 4)
```
(TL olmayan (`exchange_rate_used=null`) faturalarda `exchangeRateOrOne()` `'1.000000'` döndüğünden davranış BİREBİR AYNI kalır — regresyon riski yok.)

- [ ] `InvoiceService`/`PaymentService`/`JournalEntryService::postForInvoice()` güncelle
- [ ] `ForeignCurrencyInvoiceTest`: USD faturası (kur 30, ürün: $100 @ %20 KDV) oluşturulup posted edilince, `journal_entries`'in TL karşılığında (subtotal 100*30=3000 TL, KDV 20*30=600 TL, brüt 120*30=3600 TL) doğru kaydedildiğini doğrula; TL faturası (currencyId=null) AYNI davranışa (çevrim yok) sahip olduğunu doğrula (regresyon); geçersiz/kuru olmayan bir para birimiyle fatura oluşturma 422
- [ ] `php artisan test --compact --filter=Accounting` (TÜM Faz 9 + 10 testleri — regresyon)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): lock exchange rate on invoice/payment creation and convert journal amounts to TL`

### Task 4: Gerçekleşen kambiyo karı/zararı (kabul kriteri)

**Dosyalar:**
- Modify: `Modules/Accounting/app/Services/JournalEntryService.php` (`postForPayment()` 3 satırlı hale gelir)
- Create: `Modules/Accounting/app/Services/FxRevaluationService.php` (yalnızca `recognizeRealized()` bu görevde — `revaluateOpenBalances()` Task 5'te)
- Modify: `Modules/Accounting/app/Services/PaymentService.php` (`allocate()` para birimi eşleşme kontrolü + `FxRevaluationService` çağrısı)
- Test: `tests/Feature/Accounting/RealizedFxGainLossTest.php`

**Interfaces:** `AccountingDefaultsService::accountByCode()` (646/656 için).

```php
<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\FxRevaluation;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

/**
 * Kambiyo farkı muhasebeleşmesi (PRD 3.13 YENİ KURAL). Gerçekleşen fark =
 * (ödeme kuru - fatura kuru) × dağıtılan tutar; satışta pozitif fark kâr
 * (646), alışta pozitif fark zarar (656) — yön ters, çünkü satışta
 * alacağın değeri artması kâr, alışta borcun değeri artması zarardır.
 */
class FxRevaluationService
{
    public function recognizeRealized(Payment $payment, Invoice $invoice, string $allocatedAmount): ?FxRevaluation
    {
        if ($invoice->currency_id === null) {
            return null;
        }

        $rateDifference = bcsub($payment->exchangeRateOrOne(), $invoice->exchangeRateOrOne(), 6);

        if (bccomp($rateDifference, '0', 6) === 0) {
            return null;
        }

        $rawDifference = bcmul($allocatedAmount, $rateDifference, 4);
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
}
```

`JournalEntryService::postForPayment()` — mevcut kodu AÇIP oku, brief'teki gibi genişlet:

```php
    public function postForPayment(Payment $payment, Invoice $invoice, string $amount): JournalEntry
    {
        $cashOrBankCode = $payment->journal->type === 'cash' ? '100' : '102';
        $cashOrBank = $this->defaults->accountByCode($payment->tenant_id, $cashOrBankCode);
        $controlAccount = $this->defaults->accountByCode($payment->tenant_id, $invoice->type === 'purchase' ? '320' : '120');

        $controlAmountTL = bcmul($amount, $invoice->exchangeRateOrOne(), 4);
        $cashAmountTL = bcmul($amount, $payment->exchangeRateOrOne(), 4);
        $fxDifference = bcsub($cashAmountTL, $controlAmountTL, 4);

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

            $lines[] = $invoice->type === 'purchase'
                ? ($isGain
                    ? ['account_id' => $account->id, 'debit' => '0.0000', 'credit' => $absDifference]
                    : ['account_id' => $account->id, 'debit' => $absDifference, 'credit' => '0.0000'])
                : ($isGain
                    ? ['account_id' => $account->id, 'debit' => '0.0000', 'credit' => $absDifference]
                    : ['account_id' => $account->id, 'debit' => $absDifference, 'credit' => '0.0000']);

            // NOT: yukarıdaki iki dal aynı görünüyor ama $isGain hesaplaması type'a göre
            // zaten doğru yönü belirlediğinden tek bir dal yeterli — implementer bunu
            // sadeleştirebilir (bkz. Self-Review).
        }

        return $this->write(
            tenantId: $payment->tenant_id,
            journalType: $payment->journal->type,
            entryDate: $payment->payment_date->toDateString(),
            reference: $payment,
            lines: $lines,
        );
    }
```
**NOT (implementer için sadeleştirme):** Yukarıdaki taslak kodda `$lines[] = ...` bloğu gereksiz yere tekrarlı yazılmış (iki dal da `$isGain`'e göre zaten aynı sonucu üretiyor). Gerçek implementasyonda tek satır yeterli: `$lines[] = ['account_id' => $account->id, 'debit' => $isGain ? '0.0000' : $absDifference, 'credit' => $isGain ? $absDifference : '0.0000'];` — mantığı SADECE bu şekilde sadeleştir, hesaplama SONUCU (hangi durumda 646 mı 656 mı, borç mu alacak mı) DEĞİŞMEMELİ.

`PaymentService::allocate()`'e eklenecek (mevcut kodun BAŞINA, `unallocatedAmount()`/`remainingBalance()` kontrollerinden hemen sonra):

```php
        abort_if($payment->currency_id !== $invoice->currency_id, 422, __('Payment and invoice currencies must match.'));
```
ve `$this->journalEntries->postForPayment(...)` çağrısından SONRA:
```php
        app(FxRevaluationService::class)->recognizeRealized($payment, $invoice, $amount);
```
(Constructor injection tercih edilirse `PaymentService`'e `FxRevaluationService $fxRevaluations` eklenebilir — implementer karar verir, tutarlılık için constructor injection ÖNERİLİR.)

- [ ] `FxRevaluationService::recognizeRealized()` yaz
- [ ] `JournalEntryService::postForPayment()`'ı genişlet (yukarıdaki sadeleştirilmiş mantıkla)
- [ ] `PaymentService::allocate()`'e para birimi kontrolü + `recognizeRealized()` çağrısı ekle
- [ ] `RealizedFxGainLossTest`: **KABUL KRİTERİ TESTİ** — USD fatura (satış, kur 30, tutar $100) → tam tahsilat (kur 32) → `journal_entries`'de 646 hesabına TAM OLARAK `200.0000` TL kredi kaydedildiğini doğrula ((32-30)×100=200); AYNI senaryo AMA satınalma faturasında (kur artışı ZARAR olmalı) → 656'ya `200.0000` TL borç; kur AYNIYSA (fark yok) → 3. satır (646/656) HİÇ OLUŞMAZ, yalnızca 2 satırlı normal kayıt; farklı para birimindeki bir ödemenin farklı para birimindeki faturaya dağıtılması → 422; TL faturasında (currency_id=null) hiçbir kambiyo farkı hesaplanmaz (regresyon, `exchangeRateOrOne()` her ikisi için de `'1.000000'` olduğundan fark her zaman 0)
- [ ] `php artisan test --compact --filter=Accounting` (TAM regresyon)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `feat(accounting): realized fx gain/loss on payment allocation`

### Task 5: Dönem sonu gerçekleşmemiş değerleme

**Dosyalar:**
- Modify: `Modules/Accounting/app/Services/FxRevaluationService.php` (`revaluateOpenBalances()` eklenir)
- Test: `tests/Feature/Accounting/UnrealizedFxRevaluationTest.php`

```php
    /**
     * Dönem sonu değerleme (PRD 3.13): açık (henüz tam ödenmemiş) döviz
     * faturalarının kalan bakiyesi, güncel TCMB kuruyla yeniden değerlenir.
     * invoice.exchange_rate_used DEĞİŞMEZ — yalnızca raporlama amaçlı bir
     * kayıt üretilir (bilinçli sadeleştirme: dönem başı ters kayıt/reversal
     * bu fazın kapsamında değil, plan Architecture notuna bkz).
     *
     * @return list<FxRevaluation>
     */
    public function revaluateOpenBalances(int $tenantId, string $asOfDate, ExchangeRateService $exchangeRates, JournalEntryService $journalEntries): array
    {
        $invoices = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'posted')
            ->whereNotNull('currency_id')
            ->get()
            ->filter(fn (Invoice $invoice) => bccomp($invoice->remainingBalance(), '0', 4) > 0);

        $created = [];

        foreach ($invoices as $invoice) {
            $currentRate = $exchangeRates->lockRateFor($tenantId, $invoice->currency_id, $asOfDate);
            $rateDifference = bcsub($currentRate, $invoice->exchangeRateOrOne(), 6);

            if (bccomp($rateDifference, '0', 6) === 0) {
                continue;
            }

            $rawDifference = bcmul($invoice->remainingBalance(), $rateDifference, 4);
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
            $account = app(AccountingDefaultsService::class)->accountByCode($tenantId, $isGain ? '646' : '656');
            $controlAccount = app(AccountingDefaultsService::class)->accountByCode($tenantId, $invoice->type === 'purchase' ? '320' : '120');
            $absDifference = $isGain ? $signedDifference : bcmul($signedDifference, '-1', 4);

            $journalEntries->write(
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
```
(İmportlar: `Modules\Accounting\Models\Invoice`. `ExchangeRateService`/`JournalEntryService` parametre olarak alınıyor — implementer isterse constructor injection'a çevirebilir, tutarlılık için Task 4'teki `FxRevaluationService`'e constructor'dan `AccountingDefaultsService`, `ExchangeRateService`, `JournalEntryService` enjekte edip metod imzalarını sadeleştirmesi ÖNERİLİR.)

- [ ] `revaluateOpenBalances()` yaz (yukarıdaki mantık, implementer constructor injection'a sadeleştirebilir)
- [ ] `UnrealizedFxRevaluationTest`: USD faturası (kur 30, $100, henüz ödenmemiş) → dönem sonu güncel kur 33 ile değerleme → `fx_revaluations(type=unrealized)` kaydı `difference_amount=300.0000` (satışsa kâr) + `journal_entries`'de 120/646 dengeli kayıt; TAM ÖDENMİŞ bir fatura değerlemeye DAHİL EDİLMEZ; kur DEĞİŞMEMİŞSE hiçbir kayıt oluşmaz; `invoice.exchange_rate_used` işlem sonrası HÂLÂ orijinal (30) değerinde (değişmediği doğrulanır)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=UnrealizedFxRevaluationTest`
- [ ] Commit: `feat(accounting): period-end unrealized fx revaluation for open foreign-currency invoices`

### Task 6: e-Fatura/e-Arşiv durum makinesi (Strategy Pattern)

**Dosyalar:**
- Create: `Modules/Accounting/app/Contracts/EInvoiceProviderInterface.php`
- Create: `Modules/Accounting/app/Services/EInvoice/NullEInvoiceProvider.php`
- Create: `Modules/Accounting/app/Services/EInvoiceService.php`
- Modify: `Modules/Accounting/app/Providers/AccountingServiceProvider.php` (binding)
- Test: `tests/Feature/Accounting/EInvoiceServiceTest.php`

```php
<?php

namespace Modules\Accounting\Contracts;

use Modules\Accounting\Models\Invoice;

/**
 * e-Fatura/e-Arşiv sağlayıcı sözleşmesi (Strategy — CostingStrategyInterface
 * ile aynı desende). Gerçek entegratör (Sovos/Uyumsoft/Foriba) adaptörü
 * ayrı bir iş; bu fazda yalnızca bir null/log sağlayıcı sağlanır.
 */
interface EInvoiceProviderInterface
{
    public function send(Invoice $invoice): string; // GİB UUID döner
}
```

```php
<?php

namespace Modules\Accounting\Services\EInvoice;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Accounting\Contracts\EInvoiceProviderInterface;
use Modules\Accounting\Models\Invoice;

class NullEInvoiceProvider implements EInvoiceProviderInterface
{
    public function send(Invoice $invoice): string
    {
        Log::info('e-Invoice send (null provider)', ['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

        return (string) Str::uuid();
    }
}
```

```php
<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Contracts\EInvoiceProviderInterface;
use Modules\Accounting\Models\Invoice;

/**
 * e-Fatura durum makinesi (PRD 3.14): not_sent → sent → accepted/rejected.
 * Yalnızca posted bir fatura gönderilebilir; muhasebe kaydını (dr/cr)
 * ETKİLEMEZ — bu, InvoiceService::post()'ta ayrıca ve önceden oluşur.
 */
class EInvoiceService
{
    public function __construct(private readonly EInvoiceProviderInterface $provider) {}

    public function send(Invoice $invoice): void
    {
        abort_unless($invoice->status === 'posted', 422, __('Only a posted invoice can be sent as an e-invoice.'));
        abort_unless($invoice->e_invoice_status === 'not_sent', 422, __('This invoice has already been sent.'));

        $uuid = $this->provider->send($invoice);

        $invoice->update(['e_invoice_status' => 'sent', 'gib_uuid' => $uuid]);
    }

    public function markAccepted(Invoice $invoice): void
    {
        abort_unless($invoice->e_invoice_status === 'sent', 422, __('Only a sent e-invoice can be marked as accepted.'));

        $invoice->update(['e_invoice_status' => 'accepted']);
    }

    public function markRejected(Invoice $invoice): void
    {
        abort_unless($invoice->e_invoice_status === 'sent', 422, __('Only a sent e-invoice can be marked as rejected.'));

        $invoice->update(['e_invoice_status' => 'rejected']);
    }
}
```

`AccountingServiceProvider::register()`'a ekle: `$this->app->bind(\Modules\Accounting\Contracts\EInvoiceProviderInterface::class, \Modules\Accounting\Services\EInvoice\NullEInvoiceProvider::class);`

- [ ] `EInvoiceProviderInterface` + `NullEInvoiceProvider` + `EInvoiceService` yaz
- [ ] `AccountingServiceProvider`'a binding ekle
- [ ] `EInvoiceServiceTest`: draft bir faturayı göndermeye çalışma → 422; posted bir faturayı gönderme → `e_invoice_status='sent'` + `gib_uuid` dolu; zaten `sent` bir faturayı TEKRAR gönderme → 422; `sent`'i `accepted`/`rejected` yapma; `not_sent` veya `accepted` bir faturayı `accepted`/`rejected` yapmaya çalışma → 422 (durum makinesi yalnızca `sent`'ten geçiş kabul eder)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=EInvoiceServiceTest`
- [ ] Commit: `feat(accounting): e-invoice provider strategy and status state machine`

### Task 7: Uçtan uca kabul kriteri + tam regresyon

**Dosyalar:**
- Test: `tests/Feature/Accounting/MultiCurrencyEndToEndTest.php`
- Yeni servis kodu YOK.

Bu görev PRD'nin Faz 10 kabul kriterini TEK bir zincir halinde, sıfırdan kanıtlar (Task 4'ün testinden FARKLI olarak: burada `AccountingDefaultsService::provision()` ile TAM tenant kurulumu + `ExchangeRateService::syncFromTcmb()` ile GERÇEKÇİ bir TCMB senkron akışı + `InvoiceService::create()` ile kur kilitleme + `PaymentService`/`FxRevaluationService` ile tahsilat TEK bir uçtan uca test metodunda birleştirilir).

**`test_usd_invoice_with_rate_30_and_payment_with_rate_32_produces_a_200_try_realized_gain`**: `Http::fake()` ile 2 farklı gün için 2 farklı TCMB kuru (30 ve 32) döndür → `ExchangeRateService::syncFromTcmb()` iki kez çağır (iki farklı tarih) → USD satış faturası oluştur (ilk tarihte, kur 30 kilitlenir) → satır ekle ($100) → post → tam tahsilat oluştur (ikinci tarihte, kur 32 kilitlenir) → dağıt → `JournalEntryLine::where('account_id', $account646->id)->sum('credit')` **TAM OLARAK `200.0000`** olduğunu doğrula. Ardından TÜM `journal_entry_lines` için `SUM(debit)=SUM(credit)` (mizan) assertion'ı — Faz 9'un Task 9'undaki AYNI desen.

- [ ] `MultiCurrencyEndToEndTest` yaz
- [ ] `php artisan test --compact --filter=Accounting` (TÜM Faz 9+10 testleri)
- [ ] Tüm suite: `php artisan test --compact`
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Hafıza güncellemesi: `erp-ilerleme-durumu.md`'ye Faz 10 özeti (kur kilitleme kararı, çifte-sayım-önleme tasarımı, unrealized/reversal sadeleştirmesi, PO/SO'ya currency_id eklenmeme gerekçesi dahil)
- [ ] Commit: `feat(accounting): end-to-end multi-currency acceptance test`

## Self-Review
- PRD 3.13/3.14'teki tüm maddeler eşlendi: çoklu para birimi (Task 1/3), kur kilitleme (Task 3), TCMB senkron (Task 2), gerçekleşen kambiyo farkı (Task 4 — kabul kriteri), dönem sonu gerçekleşmemiş değerleme (Task 5), e-Fatura Strategy (Task 6).
- PO/SO'ya `currency_id` EKLENMEMESİ (PRD'nin literal şemasından bir sapma) Architecture madde 3'te gerekçelendirildi — kullanılmayan/spekülatif kolon eklememe ilkesiyle tutarlı.
- Dönem sonu değerlemenin `exchange_rate_used`'u DEĞİŞTİRMEMESİ ve reversal mekanizmasının kapsam dışı bırakılması bilinçli, dokümante edilmiş bir sadeleştirme — gerçek çifte sayım riski açıkça belirtildi.
- Tüm yeni servis parametreleri (`?int $currencyId = null`) OPSİYONEL VE TRAILING — Faz 9'un TÜM mevcut çağrıları/testleri hiçbir değişiklik olmadan çalışmaya devam etmeli; her görev bunu regresyon testiyle doğruluyor.
- Enflasyon muhasebesi (PRD'nin kendi kapsam notu: VUK Mükerrer 298, 2025-2027 askıya alındı) bilinçli olarak hiç ele alınmadı — PRD'nin kendisi de bunu bu sürümde uygulanmayacağını söylüyor.
