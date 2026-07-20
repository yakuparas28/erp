# Faz 2: Envanter Çekirdeği Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Steps use checkbox syntax.

**Goal:** PRD 3.1/3.4/4.1.1–4.1.2'deki envanter veri modeli ve iş kuralları: depo/lokasyon ağacı, UoM çevrimi, ürün/lot/barkod, stock_quants/moves, sayım fişi döngüsü (kilit + görev ayrılığı + tek seferlik fark move'ları) ve depolar arası transfer. Ardından tenant paneli ekranları.

**Architecture:** Tüm kod `Modules/Inventory` altında (nWidart v13: `app/Models`, `app/Services`, `database/migrations`). Tüm modeller `BelongsToTenant`. Stok gerçek kaynağı `stock_quants`; her move quant'ı atomik günceller. Move işaret kuralı: qty>0 → `to_location`'da artış, qty<0 → `from_location`'da azalış; transfer = bağlı iki move (kaynak −, hedef +) tek transaction. Immutable ledger: onaylanan fişler UPDATE edilmez; observer aynı reference için ikinci kez move üretmez.

**Tech Stack:** Faz 0-1c temeli. İzinler PermissionCatalog'da hazır. Web ekranları Dreams ERP kalıplarıyla (`inventory.html`, `stock-adjustment.html`, `stock-transfer.html`, `warehouse.html`, `products.html`).

## Global Constraints
- Master plan Global Constraints (decimal(15,4), DB::raw yasak, morphs+morphMap, referans birim disiplini, counting_lock 409, service ürün 422, görev ayrılığı, tenant_id explicit).
- PRD şemaları birebir; PRD'de eksik olan `warehouse_transfer_lines` ve `inventory_adjustment_lines` tabloları makul boşluk doldurma olarak eklenir (transfer/sayım satırları başka türlü temsil edilemez) — plana not düşüldü.
- MySQL/SQLite'ta unique index NULL'ları ayrı sayar → quant tekilliği (lot_id null iken) servis katmanında `lockForUpdate + firstOrCreate` ile garanti edilir.
- Yeni UI metinleri `__('EN')` + tr.json.

---

### Task 1: Migration'lar (Modules/Inventory/database/migrations)
- [x] warehouses, locations (ağaç + type + counting_lock + removal_strategy), uom_categories, uoms, product_categories (hesap kolonları nullable — Faz 9'da kullanılacak), products (tam kolon seti: uom_id, track_by, product_type, cost_method, standard_cost, avco_unit_cost, current_stock, is_kit, product_template_id), product_barcodes, product_lots, stock_quants (reserved_qty dahil), stock_moves (morphs reference), inventory_adjustments (+lines: product, counted_qty, theoretical_qty), warehouse_transfers (+lines)
- [x] `php artisan migrate` iki motorda da (MySQL + test SQLite) yeşil

### Task 2: Modeller + factory'ler + morph alias'ları
- [x] 14 model (BelongsToTenant, casts, ilişkiler, newFactory) + factory'ler
- [x] morphMap: inventory_adjustment, warehouse_transfer, stock_move, product...

### Task 3: UomConversionService + InventoryDefaultsService
- [x] `toReference(Uom $uom, string $qty): string` — factor ile çevrim (bcmath/decimal string); kategori uyuşmazlığı → InvalidArgumentException
- [x] `InventoryDefaultsService::provision(Tenant)`: Ana Depo + kök lokasyon + sanal lokasyonlar (customer/supplier/inventory_loss/transit) + 'Birim' kategorisi + 'Adet' referans birimi; TenantProvisioningService'e bağlanır
- [x] Testler

### Task 4: StockMoveService (çekirdek kural motoru)
- [x] `move(...)`: referans birime çevrim; counting_lock kontrolü (409, kilit sahibi fiş hariç); product_type=service → 422; lot zorunluluğu (track_by != none iken); move insert + quant atomik increment/decrement (tek transaction, lockForUpdate); products.current_stock özet güncelleme
- [x] Testler: quant artış/azalış, kilitli lokasyon 409, hizmet 422, birim çevrimi, lot'lu quant ayrışması, negatif stok engeli (çıkışta available kontrolü)

### Task 5: InventoryAdjustmentService (sayım döngüsü)
- [x] Yaşam döngüsü: draft→counting (lokasyonu kilitle; zaten kilitliyse 409) →pending_approval→approved/cancelled (kilidi bırak)
- [x] Sayım satırı ekleme: additive (aynı ürün+lot upsert + increment) — paralel işçi birleşmesi
- [x] Onay: izin `approve inventory adjustments` + created_by ≠ approver (403); fark = counted − theoretical(quant snapshot); fark move'ları TEK SEFER (reference kontrolü); kilit kalkar
- [x] Testler: tam döngü, çifte onay engeli, self-approval 403, additive birleşme, iptal kilidi bırakır

### Task 6: WarehouseTransferService
- [x] draft→completed: her satır için kaynakta − / hedefte + bağlı iki move, tek transaction; kilitli lokasyon 409; yetersiz stok 422
- [x] Testler

### Task 7: Web ekranları (tenant paneli, ayrı görev — Task 1-6 sonrası)
- [ ] /app/inventory/products (liste+modal CRUD, kategori, barkod), /app/inventory/warehouses (+lokasyon ağacı), /app/inventory/stock (quant listesi), /app/inventory/adjustments (fiş aç→say→onayla akışı), /app/inventory/transfers
- [ ] Sidebar 'Envanter' bölümü (izin bazlı), çeviriler, testler

## Self-Review
- PRD 3.1 (kör sayım: theoretical_qty işçiden gizlenir) Task 7 ekranında ele alınacak (rol bazlı görünürlük).
- Maliyet motoru (valuation layers) bilinçli olarak Faz 5'te — move servisinde genişleme noktası (event/hook) bırakılır.
- Sync/mobil uçları Faz 4'te; adjustment satır ekleme servisi additive olduğundan Faz 4 doğrudan üstüne oturur.
