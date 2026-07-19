# ERP Çekirdek (PRD v6.2) Master Uygulama Planı

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **NOT:** Bu bir MASTER plandır. PRD birden fazla bağımsız alt sistemi kapsadığı için her faz, uygulamaya başlamadan önce `superpowers:writing-plans` beceriyle kendi detaylı (adım adım, kod içeren) plan dosyasına açılır: `docs/superpowers/plans/YYYY-MM-DD-faz-N-<ad>.md`. Bu dosya fazların sırasını, kapsamını, tablolarını, kritik kurallarını ve kabul kriterlerini sabitler.

**Goal:** PRD v6.2'deki çok kiracılı, modüler, Türkiye mevzuatına uyumlu ERP çekirdeğini (Envanter + Satış + Satınalma + Muhasebe + Platform/Lisans + Partner Portal) Laravel 13 üzerinde uçtan uca inşa etmek.

**Architecture:** Single-database multi-tenancy (`tenant_id` + `BelongsToTenant` global scope). Dört gerçek nWidart modülü: `Modules/Inventory` (çekirdek, kapatılamaz), `Modules/Sales`, `Modules/Purchase`, `Modules/Accounting`. Platform seviyesi (Süper Admin, lisans) çekirdek `app/` içinde. Immutable ledger: stok hareketleri ve yevmiye kayıtları asla UPDATE edilmez, fark kayıtla ilerlenir. Strategy pattern: maliyet motoru (FIFO/AVCO/Standard) ve e-Fatura sağlayıcısı (`EInvoiceProviderInterface`).

**Tech Stack:** Laravel 13, PHP 8.5, MySQL (PostgreSQL-uyumlu şema), nwidart/laravel-modules v13, Laravel Sanctum (3 guard: tenant `sanctum`, `super_admin`, `partner`), spatie/laravel-permission (Teams), spatie/laravel-activitylog, Laravel Reverb, PHPUnit 12. Mobil istemci (React Native + Expo + SQLite) AYRI depodadır — bu plan yalnızca sunucu API'sini kapsar.

## Global Constraints (PRD'den birebir — her fazın görevlerine dahildir)

- **Para/miktar tipleri:** asla float/double; işaretli miktarlar `decimal(15,4)`, kesin negatif olamayanlar `unsignedDecimal(15,4)`; kur `decimal(15,6)`; UoM factor `decimal(15,6)`.
- **`DB::raw` ve DB trigger YASAK** — her şey Eloquent Query Builder (PostgreSQL geçiş hazırlığı).
- **Polymorphic ilişkiler:** `$table->morphs()` + `Relation::morphMap()` kısa alias zorunlu (`inventory_adjustment`, `warehouse_transfer`, `invoice`, `payment`, `stock_move` …). Tam class adı DB'ye yazılmaz.
- **BelongsToTenant:** tüm tenant modellerinde global scope; queue job / observer / console'da `tenant_id` DAİMA explicit parametre ile taşınır, `auth()`'a güvenilmez.
- **Immutable ledger:** onaylanan fişler/kayıtlar UPDATE edilmez; fark için yeni `stock_moves` eklenir. Observer aynı `reference_type+reference_id` için ikinci kez kayıt üretmeden önce kontrol eder.
- **Birim disiplini:** `stock_moves`/`stock_quants` DAİMA referans birimde (`uoms.is_reference=true`); çevrim yalnızca giriş sınırında (barkod okutma / web form) `factor` ile yapılır.
- **Görev ayrılığı (segregation of duty):** `created_by = auth()->id()` olan kullanıcı kendi Inventory Adjustment / PO / SO belgesini onaylayamaz — izin olsa bile.
- **Stok gerçek kaynağı:** `stock_quants` (lokasyon+lot bazlı); her `stock_moves` sonrası atomik `increment()/decrement()`. `products.current_stock` salt-okunur türetilmiş özettir.
- **Hizmet ürünü yasağı:** `product_type='service'` için `stock_moves/stock_quants/product_lots/reordering_rules/putaway_rules` oluşturma girişimi → 422; hizmette `cost_method` yalnızca `standard`.
- **Denge aksiyomu:** `journal_entries` posted'a geçmeden önce servis katmanı `SUM(debit) = SUM(credit)` doğrular, değilse 422.
- **Rezervasyon tutarlılığı:** `reserved_qty` asla `qty`'yi aşamaz (uygulama katmanı doğrulaması); SO iptalinde rezervasyon tek transaction'da geri alınır.
- **Counting lock:** `counting_lock=true` lokasyonu hedefleyen her yeni hareket → 409. FIFO katman tüketimi `lockForUpdate()` + tek transaction.
- **Sync:** cursor'ı DAİMA sunucu üretir (cihaz saati asla referans değil); bulk push 1000 satırlık chunk + chunk başına UUID; idempotency `sync_batches` unique(tenant_id, batch_uuid) + kontrol+yazma tek transaction; paralel sayım birleştirme ADDITIVE.
- **Modül erişimi:** Inventory dışındaki tüm modül route'ları `EnsureModuleActive` (`module:{key}`) middleware'i arkasında; controller içinde asla ayrıca kontrol yapılmaz. Inventory `is_core=true`, kapatılamaz.
- **Kur:** belgeye işlem tarihindeki TCMB döviz ALIŞ kuru kilitlenir (`exchange_rate_used`), onaydan sonra değişmez (VUK). Fonksiyonel para birimi TL, değiştirilemez.
- **Testler PHPUnit 12** (Pest yok); factory'ler her model için; `vendor/bin/pint --dirty --format agent` her PHP değişikliğinden sonra.
- **Kapsam dışı (yapma!):** Manufacturing/BOM, enflasyon muhasebesi, banka mutabakatı otomasyonu, çok ülkeli vergi, tam B2B portal (sipariş oluşturma/online ödeme), kullanım bazlı faturalama, iç içe kit, varyant toplu fiyat matrisi.

---

## Faz Haritası ve Bağımlılıklar

```
Faz 0 (Temel) ──► Faz 1 (Platform/Lisans) ──► Faz 2 (Envanter Çekirdeği) ──► Faz 3 (Ürün Modeli: Varyant/Kit/Hizmet)
                                                       │
                                                       ├──► Faz 4 (Sync Engine + Mobil API)
                                                       ├──► Faz 5 (Maliyet Motoru + Landed Costs)
                                                       └──► Faz 6 (Routes/Putaway/Removal + Reordering)
Faz 5,6 ──► Faz 7 (Partner + Satınalma) ──► Faz 8 (Satış + Rezervasyon + Kit Patlaması)
Faz 7,8 ──► Faz 9 (Muhasebe Çekirdeği + Fatura + Ödeme)
Faz 9 ──► Faz 10 (Çoklu Para Birimi + TR Uyum: TCMB, e-Fatura, kur farkı)
Faz 2+ ──► Faz 11 (Reverb Gerçek Zamanlı) — her fazdan sonra event eklenebilir
Faz 8,9 ──► Faz 12 (Partner Portal Girişi)
```

---

### Faz 0: Temel Altyapı (Foundation)

**Amaç:** Multi-tenancy, üç auth guard'ı, paket kurulumları ve modüler iskelet. Bundan sonraki her faz bunun üzerine oturur.

**Paketler (kullanıcı onayı alındı sayılır — PRD zorunlu kılıyor):** `nwidart/laravel-modules:^13`, `laravel/sanctum`, `spatie/laravel-permission` (Teams=tenant_id), `spatie/laravel-activitylog`.

**İşler:**
- [ ] MySQL bağlantısına geçiş (`.env` + `config/database.php` doğrulama; Herd MySQL servisi)
- [ ] `tenants` tablosu + `Tenant` modeli (id, name, accounting_mode enum: anglo_saxon/continental — Faz 9'da kullanılır)
- [ ] `app/Models/Concerns/BelongsToTenant.php` trait: global scope (`WHERE tenant_id = ?`), creating event'inde `tenant_id` otomatik doldurma; queue-güvenli tasarım (scope yalnızca HTTP yaşam döngüsünde; job'lara explicit `tenant_id`)
- [ ] `users` tablosuna `tenant_id` ekleme + Sanctum kurulumu (tenant guard)
- [ ] `super_admins` tablosu + modeli + ayrı `super_admin` Sanctum guard + `/api/central/*` route grubu iskeleti (BelongsToTenant bu guard'da HİÇ uygulanmaz)
- [ ] `partner` guard iskeleti (tablo Faz 12'de; guard config şimdi tanımlanır)
- [ ] Spatie Permission Teams modu (`team_foreign_key = tenant_id`) + temel roller: Tenant Admin, Warehouse Operator (diğer roller ilgili fazlarda eklenir)
- [ ] nWidart kurulumu + boş `Modules/Inventory`, `Modules/Sales`, `Modules/Purchase`, `Modules/Accounting` iskeletleri (`module.json` `requires` alanları: Sales→Inventory, Purchase→Inventory, Accounting→Inventory)
- [ ] `Relation::morphMap()` merkezi tanımı (AppServiceProvider)
- [ ] Temel test altyapısı: `TenantTestCase` (tenant + kullanıcı fabrikası, guard helper'ları)

**Kabul kriteri:** İki tenant'lı feature testte, tenant A kullanıcısı tenant B verisini hiçbir sorguda göremez; super_admin guard'ı scope'suz çalışır.

---

### Faz 1: Platform Yönetimi — Lisans Paketleri ve Modül Aktivasyonu (PRD 3.15–3.16)

**Tablolar:** `modules`, `license_packages`, `license_package_modules`, `tenant_subscriptions`, `tenant_module_activations`.

**İşler:**
- [ ] Migration'lar + modeller + factory'ler (modules seed: inventory `is_core=true`, sales, purchase, accounting)
- [ ] `license_packages` seed: Başlangıç (Inventory), Standart (Inv+Sales+Purchase), Premium (hepsi)
- [ ] `TenantSubscriptionObserver`: abonelik atanınca `license_package_modules` → `tenant_module_activations` (source=package) yansıtma
- [ ] Süper Admin CRUD API'leri (`/api/central/tenants`, `/api/central/subscriptions`, `/api/central/module-activations`) — manuel addon (source=manual_addon) desteği
- [ ] `EnsureModuleActive` middleware (`module:{key}`): `tenant_module_activations.is_active` kontrolü; kapalı → 403 'Bu modül paketinizde aktif değil'; `is_core` modül daima aktif
- [ ] Tüm süper admin aksiyonlarında spatie/activitylog (immutable)
- [ ] Testler: paket atama → aktivasyon yansıması; kapalı modül 403; core modül kapatılamaz; addon aktivasyonu; activity log kaydı

**Kabul kriteri:** Standart paketli tenant, `module:accounting` route'unda 403 alır; Premium'a geçince 200.

---

### Faz 2: Envanter Çekirdeği (PRD 3.1, 3.4, 4.1.1–4.1.2)

**Modül:** `Modules/Inventory`. **Tablolar:** `warehouses`, `locations` (self-referencing ağaç, type enum, counting_lock, removal_strategy kolonu Faz 6'da işlev kazanır), `uom_categories`, `uoms`, `product_categories`, `products` (basit hali: name, sku, barcode ilişkisi, track_by, product_type/cost_method kolonları burada açılır ama işlevleri Faz 3/5'te), `product_barcodes` (unique tenant+barcode, nullable uom_id), `product_lots`, `stock_quants` (unique tenant+product+location+lot), `stock_moves` (işaretli qty, morphs reference), `inventory_adjustments`, `inventory_adjustment_lines` (sayılan miktarlar — additive birleştirme burada), `warehouse_transfers`.

**Çekirdek servisler:**
- [ ] `StockMoveService`: hareket oluşturma → quant atomik increment/decrement (tek transaction); counting_lock kontrolü (409); hizmet ürünü reddi (422); referans birim doğrulaması
- [ ] `InventoryAdjustmentService`: fiş yaşam döngüsü (draft→counting→pending_approval→approved/cancelled); counting'e geçişte lokasyon kilidi; onayda fark kadar TEK SEFERLİK stock_moves üretimi (observer'da reference kontrolü); görev ayrılığı (kendi fişini onaylayamaz); onay/iptalde kilit kaldırma; theoretical_qty işçiden gizleme (API response'ta rol bazlı)
- [ ] `WarehouseTransferService`: onayda kaynak(-)/hedef(+) bağlı iki stock_moves tek transaction
- [ ] `UomConversionService`: factor ile referans birime çevrim (barkod uom_id doluysa)
- [ ] CRUD API'leri: warehouses, locations (ağaç), uoms, products, barcodes, lots, adjustments, transfers
- [ ] `products.current_stock` türetilmiş özet güncelleme (quant toplamından)

**Testler (kritik senaryolar):** kilitli lokasyona transfer → 409; fiş onayı ikinci kez move üretmez; kendi fişini onaylama → 403; birim çevrimi (koli barkodu → adet); lot'lu quant unique constraint; iki tenant izolasyonu.

**Kabul kriteri:** Tam sayım döngüsü feature testi: fiş aç → kilit → sayım gir → onayla → fark move'ları + quant güncel + kilit kalktı.

---

### Faz 3: Ürün Modeli — Varyant, Kit, Hizmet (PRD 3.17, 4.1.7)

**Tablolar:** `product_templates` (base_price), `product_attributes` (creation_mode), `product_attribute_values` (price_extra), `product_template_attribute_lines`, `product_variant_attribute_values`, `product_kit_components`.

**İşler:**
- [ ] Migration'lar + modeller + factory'ler; `products.product_template_id/product_type/is_kit` işlevselleşir
- [ ] `VariantGeneratorService`: instant → tüm kombinasyonlar queued job ile (tenant_id explicit); dynamic → SO satırında lazy oluşturma (Faz 8'de bağlanır); never → yalnız elle
- [ ] Creation mode kilidi: attribute bir template'e bağlandıktan sonra `creation_mode` değişimi → 422
- [ ] Önerilen fiyat hesabı: `base_price + SUM(price_extra)` (accessor/servis)
- [ ] Kit kuralları: kit'e quant açılmaz; `component_product_id.is_kit=true` → 422 (iç içe kit yok); kit patlaması servisi (`KitExplosionService`) — atomiklik: bileşenlerden biri yetersizse tümü reddedilir (fiili kullanım Faz 8)
- [ ] Hizmet ürünü kuralları: stok kayıtları → 422; cost_method=standard zorunlu

**Kabul kriteri:** Renk(2)×Beden(3) instant template → 6 varyant satırı, her biri bağımsız barkod/fiyat; dynamic modda 0 varyant; kilitli creation_mode 422.

---

### Faz 4: Senkronizasyon Motoru + Mobil API (PRD 3.2–3.3)

**Tablolar:** `sync_batches` (unique tenant+batch_uuid).

**İşler:**
- [ ] Delta Sync endpoint (`GET /api/sync/catalog?cursor=...`): sunucu üretimi opak cursor (ör. ulid/updated-id bileşimi), cursor'dan sonra değişen ürün+barkod+uom kayıtları + yeni cursor
- [ ] Bulk Push endpoint (`POST /api/sync/counts`): chunk (≤1000 satır) + batch_uuid; `sync_batches` insert + sayım yazma TEK transaction; duplicate UUID → idempotent 200 (işlem yapılmaz)
- [ ] Additive birleştirme: gelen her paket `inventory_adjustment_lines.real_qty` üzerine EKLER (upsert + increment), üzerine yazmaz
- [ ] Barkod çözümleme: uom_id dolu barkodda çevrim sunucuda da doğrulanır (savunma katmanı)
- [ ] Warehouse Operator rol kısıtları: yalnızca push + katalog çekme
- [ ] Testler: aynı UUID iki kez → tek yazım; paralel iki işçi aynı ürün → toplamsal; cursor kaldığı yerden devam; 1000+ satır çoklu chunk

**Kabul kriteri:** Aynı chunk'ın 3 kez retry'ı tek kayıt üretir; iki paralel push toplanır.

---

### Faz 5: Maliyet Motoru + Landed Costs (PRD 3.5–3.6, 4.1.3)

**Tablolar:** `stock_valuation_layers`, `landed_costs`, `landed_cost_lines`, `landed_cost_distributions`; `products.cost_method/standard_cost/avco_unit_cost`.

**İşler:**
- [ ] `CostingStrategyInterface` + `FifoCosting`, `AvcoCosting`, `StandardCosting` (Strategy pattern)
- [ ] Giriş move → katman oluşturma (FIFO) / AVCO yeniden hesap + önbellek / standart sabit
- [ ] Çıkış move → FIFO en eski katmandan tüketim, `lockForUpdate()` + tek transaction; AVCO güncel ortalama; standard sabit
- [ ] `cost_method` ilk hareketten sonra değiştirilemez (422)
- [ ] `LandedCostService`: split_method (by_weight/volume/quantity/current_cost) dağıtımı → distributions + katman unit_cost geriye dönük güncelleme; yalnızca fifo/avco ürün (standard → 409); `approve landed costs` izni (Tenant Admin)
- [ ] Testler: FIFO çok katman tüketimi; paralel çıkışta çifte tüketim yok (lock testi); AVCO ortalama doğruluğu; landed cost dağıtım matematiği (4 yöntem); standard ürüne landed cost 409

**Kabul kriteri:** 10₺×5 + 20₺×5 girişten 7 çıkış → COGS 10×5+20×2=90₺ (FIFO); aynı senaryoda AVCO 15×7=105₺.

---

### Faz 6: Rotalar, Putaway, Removal Strategy, Reordering (PRD 3.7–3.8)

**Tablolar:** `routes`, `route_rules`, `putaway_rules` (product_id|product_category_id), `reordering_rules` (unique tenant+product+location), `replenishment_suggestions`; `locations.removal_strategy` işlevselleşir.

**İşler:**
- [ ] `PutawayService`: girişte sequence sırasına göre ilk eşleşen kural → dest_location önerisi/ataması
- [ ] `RemovalStrategyService`: çıkışta lot_id belirtilmemişse strateji bazlı otomatik quant/lot seçimi (fifo: en eski giriş, fefo: `product_lots.expiry_date`, lifo, closest)
- [ ] Route/route_rules CRUD (`manage routes` izni) — push/pull kuralları veri modeli + çok adımlı transfer zinciri oluşturma
- [ ] `ReorderingService`: her stok hareketi sonrası forecast (qty − reserved_qty) < min_qty → suggestion (auto/manual trigger_type); öneri miktarı = max_qty − mevcut; acknowledged → Faz 7'de draft PO üretimi bağlanır
- [ ] Testler: FEFO en yakın SKT'li lotu seçer; putaway sequence önceliği; min altına düşünce öneri; consumable'da kuralların opsiyonelliği

**Kabul kriteri:** SKT'li 2 lotlu üründe çıkış FEFO ile erken SKT'yi tüketir; stok min altına inince suggestion oluşur.

---

### Faz 7: Partner + Satınalma / Procure-to-Pay (PRD 3.10, 4.1.4)

**Modül:** `Modules/Purchase` (+ `partners` Inventory/çekirdekte paylaşımlı). **Tablolar:** `partners`, `purchase_orders`, `purchase_order_lines`.

**İşler:**
- [ ] `partners` CRUD (is_customer/is_supplier, tax_number, payment_term_days)
- [ ] PO yaşam döngüsü: draft→rfq_sent→confirmed→done/cancelled; Purchasing Officer `create`, `confirm purchase orders` ayrı izin; kendi PO'sunu onaylayamaz
- [ ] PO confirmed → beklenen receipt stock_moves taslağı; fiilen teslim → move gerçekleşir + maliyet katmanı (Faz 5 entegrasyonu) + putaway (Faz 6)
- [ ] `bill_control_policy` (ordered_qty/received_qty) — fatura kontrolü Faz 9'daki invoice ile bağlanır (3-yönlü eşleştirme kancası burada hazırlanır)
- [ ] Replenishment acknowledged → varsayılan tedarikçiye draft PO+lines otomatik üretimi (Faz 6 bağlantısı)
- [ ] Rol: Purchasing Officer seed + izinler
- [ ] Testler: PO onayı receipt taslağı üretir; teslim alım FIFO katmanı ekler; self-approval 403; öneri→draft PO

**Kabul kriteri:** Uçtan uca: öneri → PO → onay → teslim alım → quant + katman güncel.

---

### Faz 8: Satış / Order-to-Cash + Rezervasyon + Kit Patlaması (PRD 3.11)

**Modül:** `Modules/Sales`. **Tablolar:** `sales_orders`, `sales_order_lines`; `stock_quants.reserved_qty` işlevselleşir.

**İşler:**
- [ ] SO yaşam döngüsü: draft→quotation_sent→confirmed→done/cancelled; Sales Rep `create`, `confirm sales orders` varsayılan Tenant Admin; self-approval yasağı
- [ ] Rezervasyon: confirmed → `reserved_qty` artışı (available = qty − reserved); reserved_qty ≤ qty doğrulaması; iptal/süre dolumu → tek transaction'da geri alma
- [ ] Teslimat: çıkış move + reserved serbest + removal_strategy ile katman/lot seçimi + COGS (Faz 5/6)
- [ ] Kit satırı: `KitExplosionService` ile bileşenlere patlama (atomik — yetersiz bileşen → tüm satır reddi); kit'in kendisi move'a girmez
- [ ] Dynamic varyant: SO satırında ilk seçimde lazy varyant oluşturma (Faz 3 bağlantısı); önerilen fiyat = base_price + price_extra (satırda değiştirilebilir)
- [ ] Hizmet ürünü satırı: stok hareketi üretmez, yalnız kalem
- [ ] Rol: Sales Representative seed + izinler
- [ ] Testler: rezervasyon aşımı reddi; iptalde geri alma; kit atomikliği; FEFO'lu teslimat COGS'u; hizmet kalemi move üretmez

**Kabul kriteri:** Uçtan uca: SO → onay (rezerve) → teslimat (COGS + quant düşer + rezerv kalkar).

---

### Faz 9: Muhasebe Çekirdeği — Fatura, Yevmiye, Ödeme (PRD 3.12, 4.1.4)

**Modül:** `Modules/Accounting`. **Tablolar:** `chart_of_accounts`, `journals`, `journal_entries`, `journal_entry_lines`, `tax_rates`, `invoices`, `invoice_lines`, `payments`, `payment_allocations`; `product_categories` hesap kolonları işlevselleşir.

**İşler:**
- [ ] Tekdüzen Hesap Planı seed'i (1-9 sınıf yapısı, 646/656 dahil) + journals seed (sale/purchase/cash/bank/stock/general)
- [ ] `tax_rates` seed: KDV %1/%8/%20 (2026) + `withholding_code` alanı
- [ ] `JournalEntryService`: otomatik kayıt üretimi — her stock_move, her posted invoice, her payment → journal_entries+lines (product_categories hesapları üzerinden); DENGE doğrulaması posted öncesi (422); hiçbir kayıt elle girilmez
- [ ] `accounting_mode` (anglo_saxon: COGS teslimatta / continental: girişte gider + dönem sonu düzeltme) — tenant bazlı
- [ ] Fatura akışı: PO/SO'dan invoice (type=purchase/sale, morphs source); vade = `payment_term_days`; posted → yevmiye + KDV satırı (tax_account_id)
- [ ] **3-Yönlü Eşleştirme:** invoice_line kaydında `purchase_order_lines.qty` vs receipt move toplamı vs faturalanan; `bill_control_policy=received_qty` iken aşım → 422
- [ ] Ödeme/tahsilat: `payments` + `payment_allocations` (kısmi dağıtım); fatura tam kapanınca status=paid
- [ ] Rol: Accountant (`manage chart of accounts`, `post journal entries`, `register payments`); yalnız Accountant/Tenant Admin posted yapabilir
- [ ] Testler: dengesiz kayıt 422; 3-yönlü eşleştirme aşımı 422; kısmi tahsilatla iki faturaya dağıtım; anglo-saxon vs continental COGS zamanlaması; otomatik yevmiye üretimi (move/invoice/payment)

**Kabul kriteri:** Alım→teslim→fatura→ödeme zinciri baştan sona doğru yevmiye seti üretir; mizan dengede.

---

### Faz 10: Çoklu Para Birimi + Türkiye Uyumu (PRD 3.13–3.14, 4.1.5)

**Tablolar:** `currencies`, `exchange_rates`, `fx_revaluations`; belge tablolarına `currency_id/exchange_rate_used`; invoices'a `e_invoice_type/gib_uuid/e_invoice_status`.

**İşler:**
- [ ] Currency/exchange_rate modelleri + seed (TRY, USD, EUR)
- [ ] TCMB günlük kur senkron job'u (scheduled; tenant_id explicit; source=tcmb; manual istisna girişi)
- [ ] Belge oluşturmada işlem tarihli TCMB ALIŞ kuru kilitleme (`exchange_rate_used`); onay sonrası değişmez
- [ ] Gerçekleşen kur farkı: ödeme kuru ≠ fatura kuru → `fx_revaluations` + otomatik 646/656 yevmiyesi
- [ ] Dönem sonu gerçekleşmemiş değerleme job'u (açık döviz bakiyeleri, güncel TCMB kuru)
- [ ] `EInvoiceProviderInterface` (Strategy) + null/log sağlayıcı implementasyonu; `e_invoice_type` (e_fatura/e_arsiv/kagit) + durum makinesi (not_sent→sent→accepted/rejected); gerçek entegratör (Sovos/Uyumsoft/Foriba) adaptörü ayrı iş
- [ ] Testler: kur kilitleme; kur farkı 646/656 yevmiyesi; dönem sonu unrealized kaydı; TCMB job'u mock ile

**Kabul kriteri:** USD fatura (kur 30) + tahsilat (kur 32) → 2×tutar kambiyo karı 646'da.

---

### Faz 11: Gerçek Zamanlı İzleme — Laravel Reverb (PRD 3.9)

**İşler:**
- [ ] Reverb kurulumu + tenant-private kanallar (`tenant.{tenant_id}.warehouse.{warehouse_id}`); yetkilendirme: Sanctum kullanıcı tenant_id ↔ kanal tenant_id eşleşmesi
- [ ] Event'ler: `StockMoveCreated`, `InventoryAdjustmentStatusChanged` (+ sonraki fazlardan SO/PO durum event'leri)
- [ ] Kural: WebSocket yalnız bildirim; kopan bağlantı sonrası Delta Sync devreye girer (source of truth değil)
- [ ] Testler: kanal yetkilendirme (yabancı tenant reddi); event broadcast assertion

**Kabul kriteri:** Tenant A kullanıcısı tenant B kanalına abone olamaz.

---

### Faz 12: Partner Portal Girişi (PRD 3.18, 4.1.8)

**Tablolar:** `partner_users`.

**İşler:**
- [ ] `partner_users` + `partner` Sanctum guard aktifleşir; hesap yalnız Tenant Admin/Sales Rep/Purchasing Officer tarafından açılır (self-signup yok)
- [ ] `/api/partner/*` route grubu; `BelongsToPartner` scope (BelongsToTenant'a EK: `WHERE partner_id = auth('partner')->partner_id`)
- [ ] Salt-okunur endpoint'ler: müşteri → kendi sales_orders/invoices(sale)/payments; tedarikçi → kendi purchase_orders/invoices(purchase)/payments
- [ ] Operasyonel/mali iç modeller (warehouses, quants, chart_of_accounts…) partner route'larında HİÇ tanımlanmaz
- [ ] Testler: başka partner'ın verisi görünmez; yazma denemesi 405/403; iç veri endpoint'i 404

**Kabul kriteri:** Partner kullanıcısı yalnız kendi belgelerini listeler; hiçbir mutasyon yapamaz.

---

## Çalışma Şekli (her faz için)

1. Faz başlamadan: `superpowers:writing-plans` ile detaylı faz planı yaz (`docs/superpowers/plans/`), TDD adımlarıyla (failing test → minimal kod → geçir → commit).
2. Uygulama: `superpowers:subagent-driven-development` veya `superpowers:executing-plans`.
3. Her PHP değişikliği sonrası `vendor/bin/pint --dirty --format agent`; ilgili testler `php artisan test --compact --filter=...`.
4. Faz bitişi: tüm suite (`php artisan test --compact`) + hafıza güncellemesi (`erp-ilerleme-durumu` memory dosyası) + commit.
5. Migration/model üretiminde `php artisan make:*` komutları ve `database-schema` Boost aracı kullanılır.

## Self-Review Notları

- PRD kapsam kontrolü yapıldı: 3.1–3.18 tüm bölümler bir faza eşlendi (3.1–3.4→Faz 2/4, 3.5–3.6→Faz 5, 3.7–3.8→Faz 6, 3.9→Faz 11, 3.10→Faz 7, 3.11→Faz 8, 3.12→Faz 9, 3.13–3.14→Faz 10, 3.15–3.16→Faz 1, 3.17→Faz 3, 3.18→Faz 12). Bölüm 5'in kapsam dışı listesi Global Constraints'e taşındı.
- `partners` tablosu Faz 7'de açılır ama Sales (Faz 8) da kullanır — Purchase önce geldiği için sorun yok.
- `products` tablosundaki tüm kolonlar (cost_method, product_type, is_kit, template_id) Faz 2'de tek migration'da açılır; işlevleri ilgili fazlarda devreye girer — böylece sürekli `Schema::table` migration'ı birikmez.
- Mobil (React Native/Expo) istemci bu depo kapsamında DEĞİL; Faz 4 yalnızca sunucu API sözleşmesini üretir.
