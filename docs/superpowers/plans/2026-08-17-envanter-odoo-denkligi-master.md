# Envanter Odoo Denkliği Master Plan (Fazlar A–F)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development.

**Goal:** `Modules/Inventory`'yi Odoo 17'nin `stock` modülüne **işlevsel olarak denkleştirmek** — mevcut 9 ekranı 25+ ekrana çıkararak Odoo'nun Envanter menü yüzeyine (Operasyon / Ürünler / Raporlar / Yapılandırma) tam eşleştirmek. Backend'de zaten var olan (SVL, StockMove, Lot, UoM, Category, Attribute) yetenekler ayrı ekranlara açılır; eksik kavramlar (Scrap, Batch Transfers, Package Types, Storage Categories, Multi-step warehouse, Pull routes) hem model hem UI olarak eklenir.

**Architecture:** Chart of Accounts / Currency / Exchange Rates ekranlarındaki BİREBİR aynı desen — `@extends('app.layouts.app')`, `permission:X,web` route grupları, `_form-modal`/`_status-badge` partial deseni, controller'da tenant-scoped Eloquent (TenantScope otomatik), model'de `BelongsToTenant`, servis katmanı transaction'da, event-driven cross-module iletişim (Faz 6/7/8 gibi). Odoo'nun kavram isimlerini (Scrap, Batch, Pull Route, Storage Category) Türkçeye ÇEVİRMEDEN İngilizce anahtar + tr.json sözlük konvansiyonuyla taşırız.

## Global Constraints

- **İzin eşlemesi (yeni izin İCAT ETMEYİN mecbur değilse)**: 
  - Master Data (Kategoriler/Attributes/UoM/Paketler/Storage Cat) → `manage products` (mevcut)
  - Scrap → yeni izin `perform scrap operations`
  - Batch Transfers → `manage warehouse transfers` (mevcut)
  - Lot/Seri No → `manage products` (mevcut)
  - Operation Types + Multi-step + Pull Routes → `manage warehouses` (mevcut)
  - Raporlar → `view stock` (mevcut)
- Her yeni ekran feature testiyle kanıtlanır (assertOk + assertSee + assertDatabaseHas + tenant scope + permission forbidden).
- Her yeni metin `lang/tr.json`'a eklenir — alfabetik sıra, ZORUNLU.
- `vendor/bin/pint --dirty --format agent` her PHP değişikliğinden sonra.
- Migration'lar `Modules/Inventory/database/migrations/` altına, timestamp konvansiyonuyla.
- Mevcut testler DEĞİŞTİRİLMEZ; yalnız SAYI güncellemesi gerekiyorsa (provisioned defaults count vb) o güncellenir.
- Servis referansları (`accountByCode('320')`, `Route::action='push'`, `is_functional=true` currency) mutlaka korunur — regresyon YASAK.
- Sidebar Odoo grup yapısı korunur (Operasyon / Ürünler / Raporlama / Yapılandırma alt-başlıkları).

---

## Faz A — Master Data (5 ekran)

Odoo'nun "Configuration → Products" altındaki tüm ayrı yönetim ekranları. Backend zaten var (`product_categories`, `product_attributes`, `uom_categories`, `uoms`), sadece dedicated CRUD ekranları eksik. Yeni tablolar: `package_types`, `storage_categories`.

### Task A1: Ürün Kategorileri CRUD ekranı (Product Categories)
- Route: `/app/inventory/categories` (index/store/update/destroy)
- Controller: `Modules/Inventory/app/Http/Controllers/CategoryController.php`
- View: `categories/index.blade.php` + `_form-modal.blade.php` (tree görünümü — Odoo'da hiyerarşik)
- Migration: `product_categories`'a `parent_id` (nullable self-FK) ekle (Odoo'daki gibi ağaç)
- Model: `ProductCategory::parent()`, `children()`
- Delete guard: alt kategorisi olan veya ürüne bağlı olan silinemez
- Test: 6 test (list/create/update/delete/tree/permission)

### Task A2: Özellikler (Attributes) dedicated ekran
- Route: `/app/inventory/attributes` (index/store/update/destroy)
- Controller: `Modules/Inventory/app/Http/Controllers/AttributeController.php`
- View: `attributes/index.blade.php` + `_form-modal.blade.php` + `_values-modal.blade.php`
- Templates ekranında attribute yönetimi yerine buradan yönetim
- Value CRUD ayrı modal (price_extra, position)
- Delete guard: template'e bağlı silinemez, değerleri de kontrol
- Test: 8 test

### Task A3: Birimler (UoM Categories + UoMs) CRUD
- Route: `/app/inventory/uoms` (index/store/update/destroy) + `/app/inventory/uom-categories`
- Controller: `Modules/Inventory/app/Http/Controllers/UomController.php` + `UomCategoryController`
- View: iki-panel (kategoriler sol, birimler sağ)
- Category başına unique reference UoM (Odoo'nun `uom_type = reference` desiği)
- Delete guard: ürüne bağlı UoM silinemez; kategori boşsa silinebilir
- Test: 8 test

### Task A4: Paket Tipleri (Package Types) CRUD
- Migration: `Modules/Inventory/database/migrations/2026_08_17_XXXXXX_create_package_types_table.php`
  - Fields: `id`, `tenant_id`, `name`, `height`, `width`, `packaging_length`, `max_weight`, `barcode` (nullable, unique per tenant)
- Model: `PackageType` (BelongsToTenant, factory)
- Route: `/app/inventory/package-types`
- Controller: `PackageTypeController`
- View: `package-types/index.blade.php` + `_form-modal.blade.php`
- Test: 6 test

### Task A5: Storage Categories CRUD + kapasite limit
- Migration: `create_storage_categories_table` + `location.storage_category_id` (nullable FK) ekle
  - `storage_categories`: `id`, `tenant_id`, `name`, `max_weight`, `allow_new_product` (enum: 'empty', 'same', 'mixed')
- Model: `StorageCategory` (BelongsToTenant)
- Location'da `storage_category` FK
- Route: `/app/inventory/storage-categories`
- Controller: `StorageCategoryController`
- View: `storage-categories/index.blade.php` + `_form-modal.blade.php`
- Delete guard: lokasyona bağlı silinemez
- Test: 6 test

### Task A6: Sidebar güncellemesi + full test/pint
- Sidebar "Yapılandırma" altına 5 yeni link (Kategoriler / Özellikler / Birimler / Paketler / Storage Cat)
- İzin kontrolü: hepsi `manage products` altında (Storage Cat ise `manage warehouses`)
- lang/tr.json: ~40 yeni çeviri
- Full test suite (`php artisan test --compact`)
- Pint

---

## Faz B — Operasyon Eksikleri (4 ekran)

### Task B1: Scrap (hurda) operasyonları
- Migration: `create_scraps_table` (id, tenant_id, product_id, qty, location_id, scrap_location_id, reason, done_by_user_id, scrapped_at)
- Yeni sanal lokasyon: `InventoryDefaultsService` provision'da "Scrap" lokasyonu ekle (`type='virtual'`)
- Model: `Scrap` (BelongsToTenant)
- Service: `ScrapService::scrap(...)` — StockMove kaydı üretir (source_type=scrap)
- Yeni izin: `perform scrap operations`
- Route + Controller + View + Test (5 test)

### Task B2: Batch Transfers (toplu transfer)
- Migration: `create_transfer_batches_table` (id, tenant_id, name, status, done_by_user_id)
- `warehouse_transfers`'a `batch_id` (nullable FK) ekle
- Service: `TransferBatchService::createBatch(...)`, `addTransfer(...)`, `complete(...)` — tüm transfer'leri sırayla complete eder
- Route + Controller + View (batch detay içinde transfer listesi) + Test (6 test)

### Task B3: Return (iade) operasyonları
- Hem PO iadesi (tedarikçiye geri) hem SO iadesi (müşteriden geri)
- Purchase/Sales order detay ekranında "İade Oluştur" butonu → yeni bir transfer taslağı üretir
- Mevcut WarehouseTransferService kullanılır, sadece yön ters
- Service: `PurchaseOrderService::createReturn(PurchaseOrder $po, array $lines)`, symmetric SO
- Test (4 test her modül için)

### Task B4: Delivery Slip yazdırma
- Sales order detay ekranında "Sevk İrsaliyesi (PDF)" butonu → `barryvdh/laravel-dompdf` ile yazdır
- Yeni bir view: `sales-orders/delivery-slip.blade.php` (yazdırma-optimized)
- Route: `/app/sales/orders/{order}/delivery-slip.pdf`
- Test (3 test)

---

## Faz C — Lot/Seri No (2 ekran)

### Task C1: Lots/Serial Numbers dedicated CRUD
- Route: `/app/inventory/lots`
- Controller: `LotController` (list all lots, filter by product/expired)
- View: liste + modal (create lot, expiry_date)
- Model zaten var (`ProductLot`), sadece UI
- Test (5 test)

### Task C2: Traceability raporu
- Route: `/app/inventory/lots/{lot}/trace`
- View: upstream (bu lot nereden geldi — hangi PO/receipt) + downstream (bu lot nereye gitti — hangi SO/delivery)
- Query: StockMove tablosundan lot_id ile filter, source_type/reference'a göre grupla
- Test (3 test)

---

## Faz D — Yapılandırma (5 ekran)

### Task D1: Operation Types (Receipts / Delivery / Internal / Scrap ayrı ekran)
- Migration: `create_operation_types_table` (id, tenant_id, warehouse_id, code, name, type enum ['incoming','outgoing','internal','scrap'], sequence_prefix)
- Model: `OperationType`
- Stock moves ve transferler için operation_type_id (nullable, geçiş)
- Route + Controller + View + Test (6 test)

### Task D2: Multi-step warehouse config
- Warehouse tablosuna `reception_steps` (enum: 'one_step','two_step','three_step') + `delivery_steps` (aynı)
- PurchaseOrder::receive() logic'i: two_step → önce staging lokasyona, sonra stok; three_step → staging → QC → stok
- SalesOrder::deliver() logic'i simetrik
- İki-step için `InventoryDefaultsService` provision'a "Alım Bekliyor" (input) ve "Sevk Bekliyor" (output) lokasyonları ekle
- Test (10 test — her step konfigürasyonu için)

### Task D3: Pull routes
- Mevcut `route_rules.action` enum'a `pull` değeri eklenmişti ama executor yok
- `RouteService::executePull(SalesOrder $so, Location $customerLocation)`: son adımdan başlar, her adım bir önceki move'un source'unu ayarlar (Odoo'nun "just-in-time" mantığı)
- SO confirm sırasında pull çalışır (opsiyonel — route configure ise)
- Test (5 test)

### Task D4: Rules kompleks yönetimi
- Route detay ekranını genişlet: her rule için `action` (push/pull/manufacture/buy), `procure_method` (make_to_stock / make_to_order), `sequence`
- Buy rule: reordering suggestion üret (Faz 6 desenli)
- Manufacture rule: ileride MRP'ye köprü (şimdilik no-op)
- Test (6 test)

### Task D5: Delivery Methods & Carriers (kargo firmaları)
- Migration: `create_delivery_carriers_table` (id, tenant_id, name, code, tracking_url_template)
- Model + Route + Controller + View (basit CRUD)
- SalesOrder'a `delivery_carrier_id` + `tracking_number` (nullable)
- Test (4 test)

---

## Faz E — Raporlar (5 ekran)

### Task E1: Moves History (StockMove log)
- Route: `/app/inventory/reports/moves`
- Controller: `MoveReportController`
- View: tablo (date/product/from/to/qty/reference/user); filtreler: date range, product, location, reference_type
- Test (4 test)

### Task E2: Inventory Valuation (SVL raporu)
- Route: `/app/inventory/reports/valuation`
- Query: `stock_valuation_layers`'dan ürün başına toplam remaining_value + qty
- View: ürün başına satır (kategori grupla)
- Test (4 test)

### Task E3: Locations report
- Route: `/app/inventory/reports/locations`
- Query: quant'lardan lokasyon başına toplam qty (ürün detayları drill-down)
- View: ağaç görünümü
- Test (3 test)

### Task E4: Forecasted Report
- Route: `/app/inventory/reports/forecasted`
- Ürün başına: mevcut stok + confirmed PO alınacak + confirmed SO gidecek = tahmin
- View: tablo + grafik (opsiyonel)
- Test (4 test)

### Task E5: Warehouse Analysis (basit)
- Route: `/app/inventory/reports/warehouse-analysis`
- Depo başına: toplam hareket sayısı, giriş qty, çıkış qty, mevcut kapasite doluluk (Storage Cat max_weight'e göre)
- Test (3 test)

---

## Faz F — İleri Düzey (opsiyonel, önceki fazlar biterken planlanır)

### Task F1: Landed Costs UI
- Backend Faz 5'te var
- Route + Controller + View (basit CRUD + validate action)

### Task F2: Consignment (konsinye)
- Yeni sanal lokasyon: "Consignment" (owner=partner)
- Owner'lı quant'lar
- Rapor: partner bazında konsinye stok

### Task F3: Reservation policies
- Product/Warehouse başına `reservation_method` (at_confirmation/manual/by_cross_docking)
- SalesOrderService::confirm() bu setting'e göre davranır

### Task F4: Barcode PWA (mobil operator UI)
- Ayrı bir Vue/Alpine mini-app
- Faz 4 sync API'lerini kullan
- Kapsam büyük — ayrı bir plan gerekir

---

## Deliverables (Faz A tamamlandığında)

- 5 yeni CRUD ekranı + 6-8 test dosyası (~35 test)
- 2 yeni migration (`package_types`, `storage_categories` + `product_categories.parent_id` + `locations.storage_category_id`)
- 2 yeni model (PackageType, StorageCategory) + 2 yeni Controller
- Sidebar Yapılandırma altına 5 yeni link
- ~40 tr.json çevirisi
- Full suite yeşil, Pint temiz

## Sonraki Adımlar (Fazlar B–F)

Faz A tamamlandıktan SONRA kullanıcıya sunulur. Kullanıcı hangi fazı yapmak istediğini seçer. Her faz kendi commit'inde gelir.
