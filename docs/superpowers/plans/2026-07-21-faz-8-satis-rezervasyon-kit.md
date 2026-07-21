# Faz 8: Satış (Order-to-Cash) + Rezervasyon + Kit Patlaması Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal:** PRD 3.11 — satış sipariş yaşam döngüsü (draft→quotation_sent→confirmed→done/cancelled), onayda `stock_quants.reserved_qty` rezervasyonu, teslimatta gerçek çıkış hareketi + COGS + rezerv serbest bırakma, kit satırlarının bileşenlere atomik patlaması, dynamic varyantın SO satırında lazy oluşturulması, hizmet satırının stok hareketi üretmemesi.

**Architecture:** `Modules/Sales` yeni modül; `partners` (Inventory) aynen Faz 7'deki gibi paylaşılır — bu kez `is_customer=true` taraf kullanılır. `SalesOrderService` PO ile simetrik (create/addLine/sendQuotation/confirm/cancel) ama ek olarak rezervasyon mantığı taşır. **Rezervasyon kapsamı (bilinçli sadeleştirme):** yalnızca `track_by='none'` VE `is_kit=false` VE `product_type≠'service'` olan satırlar onayda rezerve edilir — bu ürünlerin stoğu her zaman tek bir (`lot_id IS NULL`) quant satırında tutulduğundan rezervasyon o satıra doğrudan yazılabilir. Lot/seri takipli ürünlerde hangi lotun kullanılacağı ancak teslimat anında `RemovalStrategyService` ile belli olduğundan (Faz 6 kararı), önceden rezervasyon bu fazın kapsamı dışında bırakılır — teslimat anındaki mevcut stok kontrolü (`StockMoveService::move()`) zaten stok yetersizse 422 döner, sadece "erken rezervasyon" garantisi lot'lu ürünlerde yoktur. Kit ürünlerin kendi quant'ı hiç olmadığından (Faz 3 kararı) kit satırları da rezerve edilmez; bileşenlere patlama yalnızca teslimat anında olur. **Teslim edilen miktar takibi:** `PurchaseOrderLine::receivedQty()`'nin aksine (ledger'dan türetilir, 3-yönlü eşleştirme ihtiyacı), `SalesOrderLine.delivered_qty` DOĞRUDAN SAYILAN bir kolondur — çünkü kit satırının kendi `stock_moves` kaydı hiç yok (yalnız bileşenler hareket eder), ledger'dan türetme kit satırında anlamsız olurdu.

**Tech Stack:** Laravel 13, PHP 8.5, mevcut `Modules/Inventory` servisleri (`StockMoveService`, `CostingService`, `RemovalStrategyService` — otomatik zaten bağlı —, `KitExplosionService`, `VariantGeneratorService`), `Modules/Purchase`'daki desenle simetrik yeni `Modules/Sales`.

## Global Constraints
- Master plan + önceki tüm faz kısıtları geçerli (özellikle görev ayrılığı: `created_by` kendi SO'sunu onaylayamaz; `reserved_qty` asla `qty`'yi aşamaz; immutable ledger).
- `sales_order_lines.tax_rate_id` PO ile simetrik: nullable, FK'siz, Faz 9 kancası.
- `sales_orders.location_id`: siparişin rezerve/teslim edileceği tek lokasyon (FK'siz `unsignedBigInteger`, PO'daki `partner_id` gibi kolon konvansiyonuyla tutarlı).
- `KitExplosionService::explode()` dönüş tipi bu fazda `void`'den `array<int, StockMove>`'a genişletilir (geriye dönük uyumlu — mevcut `KitExplosionTest` dönüş değerini hiç kontrol etmiyor) çünkü teslimatta her bileşen hareketi için ayrı `CostingService::consumeOutbound()` çağrılması gerekir.
- Yeni metinler `__('EN')` (tr.json'a gerek yoksa eklenmez — mevcut fazlarda da yalnız İngilizce mesaj + `__()` sarmalayıcı kullanıldı, ayrı çeviri dosyası şart değil).

---

### Task 1: Migration'lar + modeller + factory'ler + Sales Representative rolü

**Dosyalar:**
- `Modules/Sales/database/migrations/2026_07_21_000001_create_sales_orders_tables.php`
- `Modules/Sales/app/Models/SalesOrder.php`, `SalesOrderLine.php`
- `Modules/Sales/database/factories/SalesOrderFactory.php`, `SalesOrderLineFactory.php`
- `app/Support/PermissionCatalog.php` (yeni metot), `database/seeders/RoleSeeder.php`, `PermissionSeeder.php`
- `app/Providers/AppServiceProvider.php` (morph map: `sales_order_line`)
- Test: `tests/Feature/Sales/SalesOrderModelTest.php`, `tests/Feature/RoleAndPermissionTest.php` (varsa genişlet, yoksa yeni assertion mevcut role testine eklenir — önce `grep -rl "Purchasing Officer" tests/` ile hangi test dosyasının rol/izin atamasını kontrol ettiği bulunur ve aynı desende `Sales Representative` eklenir)

Migration (PO'daki `purchase_orders`/`purchase_order_lines` ile birebir simetrik desen, `status` enum'u farklı):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('partner_id'); // müşteri
            $table->unsignedBigInteger('location_id'); // rezerve/teslim lokasyonu
            $table->unsignedBigInteger('created_by');
            $table->enum('status', ['draft', 'quotation_sent', 'confirmed', 'done', 'cancelled']);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('uom_id');
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('delivered_qty', 15, 4)->default(0);
            $table->unsignedBigInteger('tax_rate_id')->nullable(); // Faz 9: tax_rates henüz yok
            $table->timestamps();

            $table->index(['tenant_id', 'sales_order_id'], 'sol_tenant_so_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
    }
};
```

`SalesOrder` modeli (`PurchaseOrder` ile birebir simetrik):

```php
<?php

namespace Modules\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Partner;
use Modules\Sales\Database\Factories\SalesOrderFactory;

class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'partner_id',
        'location_id',
        'created_by',
        'status',
    ];

    protected static function newFactory(): SalesOrderFactory
    {
        return SalesOrderFactory::new();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }
}
```

`SalesOrderLine` modeli:

```php
<?php

namespace Modules\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Sales\Database\Factories\SalesOrderLineFactory;

class SalesOrderLine extends Model
{
    /** @use HasFactory<SalesOrderLineFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'product_id',
        'uom_id',
        'qty',
        'unit_price',
        'delivered_qty',
        'tax_rate_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'delivered_qty' => 'decimal:4',
        ];
    }

    protected static function newFactory(): SalesOrderLineFactory
    {
        return SalesOrderLineFactory::new();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
```

Factory'ler (`Modules/Purchase/database/factories/PurchaseOrderFactory.php` ve `PurchaseOrderLineFactory.php`'yi önce oku, aynı desende `tenant_id`/ilişkili factory `for()` çağrılarıyla yaz — `SalesOrderFactory`'de `status` varsayılanı `'draft'`, `location_id` için `Location::factory()`; `SalesOrderLineFactory`'de `qty`/`unit_price` `fake()->randomFloat` yerine proje genelinde bcmath uyumlu string sabitler kullanılıyor, PO factory'sindeki deseni birebir kopyala).

`PermissionCatalog::salesRepresentativeDefaults()` ekle (mevcut `purchasingOfficerDefaults()`'un hemen altına):

```php
    /**
     * @return list<string>
     */
    public static function salesRepresentativeDefaults(): array
    {
        return ['create sales orders', 'manage partners'];
    }
```

`RoleSeeder::run()`'a ekle: `Role::findOrCreate('Sales Representative', 'web');` (Purchasing Officer satırının hemen altına).

`PermissionSeeder::run()`'a ekle: `Role::findByName('Sales Representative', 'web')->syncPermissions(PermissionCatalog::salesRepresentativeDefaults());`

`AppServiceProvider::configureMorphMap()`'e ekle: `'sales_order_line' => SalesOrderLine::class,` (use import: `Modules\Sales\Models\SalesOrderLine`).

- [ ] Migration + modeller + factory'ler yaz; `php artisan migrate --no-interaction` çalıştır
- [ ] `PermissionCatalog`/`RoleSeeder`/`PermissionSeeder`/`AppServiceProvider` güncelle
- [ ] `tests/Feature/Sales/SalesOrderModelTest.php` yaz: SO/SOL ilişkileri (`partner`, `location`, `creator`, `lines`, `salesOrder`, `product`, `uom` doğru döner); `delivered_qty` varsayılan `'0.0000'`
- [ ] Rol/izin testi: `Sales Representative` rolü `create sales orders` ve `manage partners` iznine sahip, `confirm sales orders`'a sahip DEĞİL (Purchasing Officer testinin aynısı desende)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=SalesOrderModelTest`
- [ ] Commit: `feat(sales): sales order/line data model + Sales Representative role`

### Task 2: SalesOrderService — yaşam döngüsü + rezervasyon

**Dosyalar:**
- `Modules/Sales/app/Services/SalesOrderService.php`
- Test: `tests/Feature/Sales/SalesOrderLifecycleTest.php`, `tests/Feature/Sales/SalesOrderReservationTest.php`

```php
<?php

namespace Modules\Sales\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockQuant;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;

/**
 * Satış sipariş yaşam döngüsü (PRD 3.11): draft→quotation_sent→confirmed→
 * done/cancelled. Görev ayrılığı: oluşturan kullanıcı kendi SO'sunu
 * onaylayamaz. Onayda rezervasyon yalnızca track_by='none' + is_kit=false +
 * product_type≠'service' satırlarda uygulanır (bkz. plan Architecture notu).
 */
class SalesOrderService
{
    public function create(int $tenantId, int $partnerId, int $locationId, User $creator): SalesOrder
    {
        $so = new SalesOrder([
            'partner_id' => $partnerId,
            'location_id' => $locationId,
            'created_by' => $creator->id,
            'status' => 'draft',
        ]);
        $so->tenant_id = $tenantId;
        $so->save();

        return $so;
    }

    public function addLine(SalesOrder $so, int $productId, int $uomId, string $qty, string $unitPrice): SalesOrderLine
    {
        abort_unless($so->status === 'draft', 422, __('Lines can only be added to a draft sales order.'));

        $line = new SalesOrderLine([
            'sales_order_id' => $so->id,
            'product_id' => $productId,
            'uom_id' => $uomId,
            'qty' => $qty,
            'unit_price' => $unitPrice,
        ]);
        $line->tenant_id = $so->tenant_id;
        $line->save();

        return $line;
    }

    public function sendQuotation(SalesOrder $so): void
    {
        abort_unless($so->status === 'draft', 422, __('Only draft sales orders can be sent as a quotation.'));

        $so->update(['status' => 'quotation_sent']);
    }

    public function confirm(SalesOrder $so, User $approver): void
    {
        abort_unless($so->status === 'quotation_sent', 422, __('Only a quotation-sent sales order can be confirmed.'));
        abort_if($so->created_by === $approver->id, 403, __('You cannot confirm a sales order you created.'));
        abort_unless($approver->can('confirm sales orders'), 403, __('You are not allowed to confirm sales orders.'));

        DB::transaction(function () use ($so): void {
            foreach ($so->lines as $line) {
                $this->reserveLine($so, $line);
            }

            $so->update(['status' => 'confirmed']);
        });
    }

    public function cancel(SalesOrder $so): void
    {
        abort_if(in_array($so->status, ['done', 'cancelled'], true), 422, __('This sales order is already finalized.'));

        DB::transaction(function () use ($so): void {
            if ($so->status === 'confirmed') {
                foreach ($so->lines as $line) {
                    $remaining = bcsub($line->qty, $line->delivered_qty, 4);

                    if (bccomp($remaining, '0', 4) > 0) {
                        $this->releaseReservation($so, $line, $remaining);
                    }
                }
            }

            $so->update(['status' => 'cancelled']);
        });
    }

    private function reserveLine(SalesOrder $so, SalesOrderLine $line): void
    {
        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        if (! $this->isReservable($product)) {
            return;
        }

        $quant = StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $so->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $so->location_id)
            ->whereNull('lot_id')
            ->lockForUpdate()
            ->first();

        if ($quant === null) {
            $quant = new StockQuant([
                'product_id' => $product->id,
                'location_id' => $so->location_id,
                'lot_id' => null,
                'qty' => '0',
            ]);
            $quant->tenant_id = $so->tenant_id;
            $quant->save();
        }

        $newReserved = bcadd($quant->reserved_qty, $line->qty, 4);

        abort_if(bccomp($newReserved, $quant->qty, 4) > 0, 422, __('Insufficient available stock to reserve.'));

        $quant->update(['reserved_qty' => $newReserved]);
    }

    private function releaseReservation(SalesOrder $so, SalesOrderLine $line, string $qty): void
    {
        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        if (! $this->isReservable($product)) {
            return;
        }

        StockQuant::withoutGlobalScopes()
            ->where('tenant_id', $so->tenant_id)
            ->where('product_id', $product->id)
            ->where('location_id', $so->location_id)
            ->whereNull('lot_id')
            ->decrement('reserved_qty', $qty);
    }

    private function isReservable(Product $product): bool
    {
        return $product->track_by === 'none' && ! $product->is_kit && $product->product_type !== 'service';
    }
}
```

- [ ] Servisi yaz
- [ ] `SalesOrderLifecycleTest`: tam döngü draft→quotation_sent→confirmed; self-approval 403; izinsiz kullanıcı (Sales Rep, confirm izni yok) başkasının SO'sunu onaylayamaz 403 (Faz 7'deki `test_user_without_confirm_permission_cannot_confirm_someone_elses_order` deseniyle — AYNI kullanıcı hem oluşturup hem izinsiz onaylamaya çalışırsa, hangi kontrolün (self-approval mı izin mi) tetiklendiği belirsizleşir, bu yüzden İKİ FARKLI kullanıcı kullan); draft olmayanı tekrar confirm 422; done/cancelled'ı tekrar cancel 422
- [ ] `SalesOrderReservationTest`: track_by=none üründe confirm → `reserved_qty` artışı (line.qty kadar); stok yetersizken confirm → 422 ve `reserved_qty` DEĞİŞMEMİŞ (transaction rollback); kit üründe confirm → reserved_qty değişmez (quant hiç yok/0 kalır); service üründe confirm → reserved_qty değişmez; cancel (confirmed durumdan) → reserved_qty tamamen geri alınır
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=SalesOrder`
- [ ] Commit: `feat(sales): sales order lifecycle with reservation and segregation of duty`

### Task 3: KitExplosionService dönüş tipi genişletmesi (teslimat hazırlığı)

**Dosyalar:**
- Modify: `Modules/Inventory/app/Services/KitExplosionService.php`
- Test: mevcut `tests/Feature/Inventory/KitExplosionTest.php` yeniden çalıştırılır (regresyon — dönüş değeri kontrol edilmiyor ama davranış aynı kalmalı)

`explode()` metodunun imzasını `void`'den `array`'e çevir, her bileşen hareketini topla:

```php
    /**
     * @return array<int, \Modules\Inventory\Models\StockMove>
     */
    public function explode(
        Product $kit,
        string $kitQty,
        ?int $fromLocationId,
        ?int $toLocationId,
        string $referenceType,
        int $referenceId,
    ): array {
        abort_unless($kit->is_kit, 422, __('This product is not a kit.'));

        $components = ProductKitComponent::where('kit_product_id', $kit->id)->with('componentProduct')->get();

        foreach ($components as $component) {
            abort_if($component->componentProduct->is_kit, 422, __('A kit component cannot itself be a kit.'));
        }

        return DB::transaction(function () use ($components, $kitQty, $fromLocationId, $toLocationId, $referenceType, $referenceId): array {
            $moves = [];

            foreach ($components as $component) {
                $requiredQty = bcmul($component->qty, $kitQty, 4);

                $moves[] = $this->stockMoves->move(
                    tenantId: $component->tenant_id,
                    product: $component->componentProduct,
                    fromLocationId: $fromLocationId,
                    toLocationId: $toLocationId,
                    qty: $fromLocationId !== null ? bcmul($requiredQty, '-1', 4) : $requiredQty,
                    uom: $component->componentProduct->uom,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                );
            }

            return $moves;
        });
    }
```

- [ ] `KitExplosionService::explode()` dönüş tipini değiştir (yukarıdaki kod)
- [ ] `php artisan test --compact --filter=KitExplosionTest` — mevcut 5 test hâlâ geçmeli (regresyon)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Commit: `refactor(inventory): kit explosion returns created component moves`

### Task 4: Teslimat — StockMoveService + CostingService + KitExplosionService entegrasyonu

**Dosyalar:**
- Modify: `Modules/Sales/app/Services/SalesOrderService.php` (constructor + `deliver()` eklenir)
- Modify: `Modules/Sales/app/Models/SalesOrderLine.php` gerek yok (delivered_qty zaten Task 1'de var)
- Test: `tests/Feature/Sales/SalesOrderDeliveryTest.php`

**Interfaces:**
- Consumes: `StockMoveService::move()` (Task'lar öncesi mevcut), `CostingService::consumeOutbound(Product, StockMove, string $qty): string` (Faz 5), `KitExplosionService::explode(...): array<StockMove>` (Task 3'te güncellendi)
- Produces: `SalesOrderService::deliver(SalesOrderLine $line, string $qty): void`

`SalesOrderService` constructor'ı ve `deliver()`:

```php
    public function __construct(
        private readonly StockMoveService $stockMoves,
        private readonly CostingService $costing,
        private readonly KitExplosionService $kitExplosion,
    ) {}
```

```php
    /**
     * Fiili teslimat (PRD 3.11): hizmet satırı hiçbir şey üretmez; kit
     * satırı bileşenlere patlar (kit'in kendisi asla move'a girmez);
     * normal satır tek bir çıkış hareketi + COGS üretir ve (rezerve
     * edilmişse) rezervi serbest bırakır. Tüm satırlar tam teslim
     * edilince SO 'done' durumuna geçer.
     */
    public function deliver(SalesOrderLine $line, string $qty): void
    {
        $so = $line->salesOrder;

        abort_unless($so->status === 'confirmed', 422, __('Only a confirmed sales order can be delivered.'));

        $remaining = bcsub($line->qty, $line->delivered_qty, 4);
        abort_if(bccomp($qty, $remaining, 4) > 0, 422, __('Delivered quantity cannot exceed the remaining ordered quantity.'));

        $product = Product::withoutGlobalScopes()->findOrFail($line->product_id);

        if ($product->product_type === 'service') {
            $line->increment('delivered_qty', $qty);
            $this->markDoneIfFullyDelivered($so);

            return;
        }

        if ($product->is_kit) {
            $moves = $this->kitExplosion->explode(
                kit: $product,
                kitQty: $qty,
                fromLocationId: $so->location_id,
                toLocationId: null,
                referenceType: 'sales_order_line',
                referenceId: $line->id,
            );

            foreach ($moves as $move) {
                $moveProduct = Product::withoutGlobalScopes()->findOrFail($move->product_id);
                $this->costing->consumeOutbound($moveProduct, $move, bcmul($move->qty, '-1', 4));
            }

            $line->increment('delivered_qty', $qty);
            $this->markDoneIfFullyDelivered($so);

            return;
        }

        $move = $this->stockMoves->move(
            tenantId: $so->tenant_id,
            product: $product,
            fromLocationId: $so->location_id,
            toLocationId: null,
            qty: bcmul($qty, '-1', 4),
            uom: $line->uom,
            referenceType: 'sales_order_line',
            referenceId: $line->id,
        );

        $this->costing->consumeOutbound($product, $move, $qty);

        if ($this->isReservable($product)) {
            $this->releaseReservation($so, $line, $qty);
        }

        $line->increment('delivered_qty', $qty);
        $this->markDoneIfFullyDelivered($so);
    }

    private function markDoneIfFullyDelivered(SalesOrder $so): void
    {
        $so->refresh();

        $fullyDelivered = $so->lines->every(fn (SalesOrderLine $line) => bccomp($line->fresh()->delivered_qty, $line->qty, 4) === 0);

        if ($fullyDelivered) {
            $so->update(['status' => 'done']);
        }
    }
```

(`Product`, `StockMoveService` importları zaten dosyada var; `KitExplosionService`, `CostingService` importlarını ekle.)

- [ ] `SalesOrderService` constructor'ını ve `deliver()`/`markDoneIfFullyDelivered()`'ı ekle
- [ ] `SalesOrderDeliveryTest`: normal ürün teslimatı → çıkış move + `StockValuationLayer` COGS düşüşü (Faz 5 testlerindeki assertion deseni) + reserved_qty serbest kalır + delivered_qty artar; kit satırı teslimatı → bileşenlere move + her bileşen için COGS + kit'in kendi move'u YOK; hizmet satırı teslimatı → hiçbir `stock_moves` kaydı yok, sadece delivered_qty artar; tüm satırlar tam teslim edilince SO status='done'; kısmi teslimat sonrası kalan miktardan fazla teslim etmeye çalışma → 422; confirmed olmayan SO'ya teslimat → 422
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=SalesOrderDeliveryTest`
- [ ] Commit: `feat(sales): delivery with stock move, COGS, kit explosion and reservation release`

### Task 5: Dynamic varyant SO entegrasyonu + tam uçtan uca akış testi

**Dosyalar:**
- Test: `tests/Feature/Sales/DynamicVariantSalesOrderTest.php`, `tests/Feature/Sales/SalesOrderEndToEndTest.php`
- Yeni servis kodu YOK — mevcut `VariantGeneratorService::resolveOrCreateDynamic()` (Faz 3) ve `SalesOrderService` (Task 2/4) doğrudan birlikte kullanılır.

`DynamicVariantSalesOrderTest`: `creation_mode='dynamic'` bir attribute'a bağlı template'te henüz hiç varyant yokken, `VariantGeneratorService::resolveOrCreateDynamic($template, $attributeValueIds)` çağrılıp dönen `Product`'ın id'si `SalesOrderService::addLine()`'a verilir; satır kaydedildiğinde o ürünün gerçekten oluştuğu ve `recommendedPrice()` ile hesaplanan tutarın satırın `unit_price`'ı olarak kullanılabildiği doğrulanır (iki kez aynı `attributeValueIds` ile çağrılırsa aynı ürün döner — idempotency, `VariantGeneratorTest`'teki mevcut davranışın SO bağlamında regresyonu).

`SalesOrderEndToEndTest`: kabul kriteri senaryosu — SO oluştur → satır ekle (stoklu, track_by=none ürün) → quotation gönder → confirm (rezerve olur) → deliver (COGS + quant düşer + rezerv kalkar + SO done). Tek test metodu, PRD'nin "Kabul kriteri" cümlesini birebir kod olarak kanıtlar.

- [ ] İki test dosyasını yaz (yukarıdaki senaryolar, gerçek assertion kodu her ikisinde de tam yazılır — bkz. `tests/Feature/Purchase/ReplenishmentToDraftPurchaseOrderTest.php` ve `PurchaseOrderReceiptTest.php`'teki detay seviyesi referans alınır)
- [ ] `php artisan test --compact --filter=Sales`
- [ ] Tüm suite: `php artisan test --compact`
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Hafıza güncellemesi: `erp-ilerleme-durumu.md`'ye Faz 8 özeti (rezervasyon kapsamı sadeleştirmesi, delivered_qty'nin neden sayılan kolon olduğu, kit teslimat entegrasyonu dahil)
- [ ] Commit: `feat(sales): dynamic variant SO integration + end-to-end order-to-cash test`

## Self-Review
- PRD 3.11'deki tüm maddeler eşlendi: SO yaşam döngüsü (Task 1-2), rezervasyon (Task 2), teslimat/COGS (Task 4), kit patlaması (Task 3-4), dynamic varyant (Task 5), hizmet satırı (Task 4), Sales Representative rolü (Task 1).
- Rezervasyonun lot'lu ürünlerde ve kit'te uygulanmaması bilinçli, dokümante edilmiş bir sadeleştirme — Faz 6'daki "closest≈fifo" ve Faz 7'deki "confirmed PO gerçek move üretmez" kararlarıyla aynı desende.
- `delivered_qty`'nin PurchaseOrderLine'ın aksine türetilmeyip sayılan bir kolon olması, kit satırının kendi stock_move'u olmamasından kaynaklanan zorunlu bir asimetri — Architecture bölümünde gerekçelendirildi.
- `KitExplosionService::explode()` dönüş tipi değişikliği geriye dönük uyumlu (mevcut testler dönüş değerini kullanmıyor); Task 3'te ayrı bir commit olarak izole edildi ki regresyon net görülsün.
