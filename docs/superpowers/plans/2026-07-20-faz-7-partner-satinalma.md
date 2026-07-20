# Faz 7: Partner + Satınalma (Procure-to-Pay) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal:** PRD 3.10/4.1.4 — birleşik `partners` (müşteri/tedarikçi), satınalma sipariş döngüsü (draft→rfq_sent→confirmed→done/cancelled), gerçek teslim alım + maliyet katmanı + putaway entegrasyonu, 3-yönlü eşleştirme için `received_qty` kancası, ve Faz 6'nın `ReplenishmentAcknowledged` olayından otomatik draft PO üretimi.

**Architecture:** `partners` `Modules/Inventory` altında (Sales+Purchase'ın paylaştığı çekirdek kavram, master planda da böyle not düşülmüş). `purchase_orders`/`purchase_order_lines` `Modules/Purchase` altında. **Mimari sadeleştirme (bilinçli PRD yorumu):** `stock_moves` şemasında "draft/beklenen" durumu yok (Faz 2'den beri hareketler anında uygulanan, değiştirilemez defter kayıtları). Bu nedenle PO `confirmed` olduğunda gerçek bir stock_move OLUŞTURULMAZ — "beklenen teslimat" doğrudan PO'nun `confirmed` durumu + satırlarıyla temsil edilir. Gerçek stok hareketi yalnızca fiili teslim alımda (`receive()`) üretilir ve o anda `CostingService::recordInbound` (satırın `unit_price`'ı ile) + `PutawayService` (hedef öneri, farklıysa ek transfer) çağrılır. `received_qty`, satıra bağlı `stock_moves` toplamından türetilir (ayrı bir sayaç kolonu YOK — PRD 3.10 zaten "ilgili receipt stock_moves toplamı" diyor).

## Global Constraints
- Master plan + önceki faz kısıtları geçerli (özellikle görev ayrılığı: oluşturan onaylayamaz).
- `products.default_supplier_id` (nullable, FK'siz) — "varsayılan tedarikçi" PRD'de örtük, model karşılığı yok; minimal boşluk doldurma.
- `purchase_order_lines.tax_rate_id` PRD şemasında var ama `tax_rates` tablosu henüz yok (Faz 9) — nullable, FK'siz kolon olarak şimdiden eklenir (PRD'ye birebir).
- `manage partners` izni yeni: `core` grubuna eklenir (partners çekirdek/paylaşımlı olduğu için); Purchasing Officer varsayılanına da eklenir.
- Yeni metinler `__('EN')` + tr.json.

---

### Task 1: partners tablosu + model (Inventory) + Purchasing Officer rolü
- [ ] `partners` migration (PRD 4.1.4 birebir) + model + factory (Inventory modülünde)
- [ ] `PermissionCatalog`: core'a `manage partners`; yeni `purchase` grubu zaten var (`create/confirm purchase orders`)
- [ ] `RoleSeeder`: `Purchasing Officer` rolü eklenir; `PermissionSeeder`: `purchasingOfficerDefaults()` = `['create purchase orders', 'manage partners']`
- [ ] Testler: partner CRUD (is_customer/is_supplier bağımsız true/false olabilir), rol/izin ataması

### Task 2: purchase_orders/lines migration + model + `products.default_supplier_id`
- [ ] Migration'lar (Modules/Purchase) + modeller + factory'ler
- [ ] `PurchaseOrderLine::receivedQty(): string` — `stock_moves` where `reference_type='purchase_order_line'` and `reference_id=line.id` toplamı
- [ ] Testler: model ilişkileri, receivedQty boşken '0.0000'

### Task 3: PurchaseOrderService — yaşam döngüsü + görev ayrılığı
- [ ] `create/addLine/sendRfq/confirm(po, approver)`: `confirm purchase orders` izni + `created_by === approver.id` → 403
- [ ] Testler: tam döngü draft→rfq_sent→confirmed; self-approval 403; izinsiz kullanıcı 403; draft olmayanı tekrar confirm 422

### Task 4: Teslim alım — StockMoveService + CostingService + PutawayService entegrasyonu
- [ ] `PurchaseOrderService::receive(PurchaseOrderLine, string $qty, int $receivingLocationId, ?int $lotId)`: yalnızca `confirmed` PO'da; `StockMoveService::move()` (reference: purchase_order_line) + `CostingService::recordInbound(product, move, qty, line.unit_price)`; `PutawayService::resolveDestination` farklı bir hedef önerirse ek bir transfer (`StockMoveService`, maliyet yeniden kaydedilmez — transferler değer yaratmaz, Faz5 kararıyla tutarlı)
- [ ] `receivedQty()` teslimden sonra doğru toplanır; `bill_control_policy` alanı okunabilir (Faz 9 hook, davranış yok)
- [ ] Testler: teslim alım FIFO katmanı ekler (unit_price'tan); putaway hedefi farklıysa iki hareket (al + yerleştir); confirmed olmayan PO'ya teslim 422

### Task 5: Replenishment → draft PO (Faz 6 entegrasyonu)
- [ ] `ReorderingService::acknowledge(ReplenishmentSuggestion, User $user)` — imza `User` alır (event'e taşımak için, Faz 6'nın küçük ama gerekli genişletmesi)
- [ ] `ReplenishmentAcknowledged` event'ine `User $user` eklenir
- [ ] `Modules/Purchase` listener: ürünün `default_supplier_id`'si varsa draft PO + satır otomatik oluşturur (creator = acknowledge eden kullanıcı — görev ayrılığı devam eder: bu PO'yu O KULLANICI onaylayamaz)
- [ ] Testler: acknowledge → draft PO oluşur (doğru ürün/miktar/tedarikçi); default_supplier_id yoksa PO oluşmaz (log/no-op)

## Self-Review
- PRD 3.10 "beklenen receipt stock_moves taslağı" kelimesi bilinçli olarak "PO confirmed durumu + satırlar" ile karşılandı, gerçek hareket yalnız teslimde üretiliyor — mimari not olarak yukarıda açıklandı.
- 3-yönlü eşleştirme (fatura karşılaştırması) Faz 9'da; bu faz yalnızca `receivedQty()` kancasını hazırlıyor.
- `partners` Inventory'de olduğundan Sales (Faz 8) aynı tabloyu sorunsuz kullanacak.
