# Faz 6: Rotalar, Putaway, Removal Strategy, Reordering Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal:** PRD 3.7–3.8 — otomatik yerleşim (putaway), otomatik çıkış stratejisi (FIFO/FEFO/LIFO), çok adımlı rota (push), stok min altına düşünce otomatik yeniden sipariş önerisi.

**Architecture:** `RemovalStrategyService` `StockMoveService::move()`'a entegre edilir ama YALNIZCA outbound çağrılarda ve yalnızca `lotId` verilmemişse devreye girer (inbound hâlâ lot'u zorunlu ister — yeni parti tahmin edilemez). `ReorderingService` da `StockMoveService`'e entegre edilir (PRD: "her stok hareketinden sonra" değerlendirilir) — costing motorunun aksine bu OTOMATİK bağlanır. `PutawayService` ve `RouteService` bağımsız, çağrıcı tarafından tetiklenen servislerdir (henüz PO/SO yok, doğrudan test/servis çağrısıyla kanıtlanır).

## Global Constraints
- Master plan + önceki faz kısıtları geçerli.
- Mevcut Faz 2 testi `test_lot_tracked_product_requires_lot_and_serial...` KIRILMAMALI (inbound + lot yok → hâlâ 422).
- `putaway_rules` tablosu PRD'nin 4.1.4'teki düzeltmesiyle BİREBİR: `product_category_id` baştan eklenir (Faz 2'de olduğu gibi sonradan ALTER yapmaya gerek yok).
- Yeni metinler `__('EN')` + tr.json.

---

### Task 1: Migration'lar + modeller
- [ ] `routes` (name), `route_rules` (route_id, from_location_id, to_location_id, action enum push/pull, sequence)
- [ ] `putaway_rules` (product_id nullable, product_category_id nullable, source_location_id, dest_location_id, sequence)
- [ ] `reordering_rules` (product_id, location_id, min_qty, max_qty, trigger_type enum auto/manual, unique tenant+product+location)
- [ ] `replenishment_suggestions` (reordering_rule_id, suggested_qty, status enum pending/acknowledged/dismissed)
- [ ] Modeller + factory'ler

### Task 2: RemovalStrategyService + StockMoveService entegrasyonu
- [ ] `RemovalStrategyService::selectLot(Product, int $locationId): ?int` — lokasyonun `removal_strategy`'sine göre (fifo: en eski quant, fefo: en yakın `product_lots.expiry_date`, lifo: en yeni quant, closest: fifo'ya eşdeğer — tek lokasyon içinde mesafe farkı yok, dokümante edilir)
- [ ] `StockMoveService::move()`: outbound + lotId=null + track_by≠none → önce `selectLot` dener, bulamazsa hâlâ 422
- [ ] Testler: FEFO en yakın SKT'yi seçer; LIFO en yeni partiyi seçer; hiç quant yoksa 422; inbound'da hâlâ lot zorunlu (regresyon)

### Task 3: PutawayService
- [ ] `resolveDestination(Product, int $sourceLocationId): ?int` — ürüne özel VEYA kategori bazlı kuralları `sequence` sırasına göre tarar, ilk eşleşen `dest_location_id`'yi döner
- [ ] Testler: ürün bazlı kural kategori bazlıdan önce geldiğinde sequence kazanır; eşleşme yoksa null

### Task 4: Route/RouteRule + RouteService (push zinciri)
- [ ] CRUD yok (bu fazda ekran değil); `RouteService::executePush(Route, Product, string $qty, Uom): void` — `route_rules`'ı sequence sırasına göre gezip yalnız `action=push` adımlarında `StockMoveService`'i zincirleme çağırır (ör. 'İki Adımlı Teslimat': Depo→Ara Bölge→Sevk Alanı)
- [ ] `manage routes` izni CRUD ekranı olmadığından bu fazda yalnız test/servis seviyesinde
- [ ] Testler: 2 adımlı push rota, son lokasyonda doğru miktar; pull adımı otomatik çalışmaz (yalnız veri, Faz 7/8'e bırakılır)

### Task 5: ReorderingService + StockMoveService entegrasyonu
- [ ] `evaluate(Product, int $locationId)`: eşleşen `reordering_rules` yoksa no-op; forecast = quant.qty − reserved_qty; forecast < min_qty ise `trigger_type=auto` → `pending` öneri oluşturur/günceller (suggested_qty = max_qty − forecast); `manual` → otomatik oluşturmaz
- [ ] `acknowledge(ReplenishmentSuggestion)`: status→acknowledged + `ReplenishmentAcknowledged` event fırlatır (Faz 7'nin draft PO üretmesi için hazır kanca — bu fazda listener yok)
- [ ] `StockMoveService::move()` sonunda ilgili lokasyon için `evaluate()` otomatik çağrılır (PRD: her hareketten sonra)
- [ ] Testler: min altına düşünce auto öneri oluşur; manual tetikte otomatik oluşmaz; zaten pending öneri varsa tekrar oluşturulmaz (güncellenir); consumable/kural tanımsız üründe no-op

## Self-Review
- PRD 3.7 "closest" stratejisi tek-lokasyon modelimizde fifo'ya eşdeğer — bu bilinçli basitleştirme koda yorum olarak düşüldü.
- `manage routes`/`manage reordering rules` CRUD ekranları bu fazda YOK (master planda da yok); yalnızca motor + veri modeli.
- Reordering'in StockMoveService'e otomatik bağlanması PRD'nin açık ifadesiyle (costing'in aksine) tutarlı.
