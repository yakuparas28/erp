# Faz 5: Maliyet Motoru + Landed Costs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal:** PRD 3.5–3.6/4.1.3 — FIFO/AVCO/Standart maliyet motoru (Strategy pattern) ve landed cost (ek maliyet) dağıtımı. Bu faz saf backend/servis katmanıdır; master planda ekran görevi yok (mevcut inventory ekranları bir sonraki fazlarda genişletilecek).

**Architecture:** `stock_valuation_layers` her GERÇEK değer değişikliği yaratan hareket için bir satır (giriş: pozitif qty/unit_cost, çıkış: negatif qty). Motor `StockMoveService`'e OTOMATİK bağlanmaz — çünkü transferler (iki dahili lokasyon arası) değer yaratmaz/tüketmez, yalnızca gerçek giriş/çıkışların (sayım fişi düzeltmeleri, ileride PO/SO) maliyeti olur. `CostingService` çağrıcı tarafından (ör. ileride PO/SO servisleri, şimdilik doğrudan test/servis çağrısı) `recordInbound`/`consumeOutbound` ile açıkça tetiklenir. `products.weight/volume` (nullable decimal) bu fazda eklenir — `by_weight`/`by_volume` landed cost dağıtımı için yapısal olarak gerekli, PRD'de dolaylı olarak varsayılan minimal ek.

## Global Constraints
- Master plan Global Constraints geçerli (decimal(15,4), DB::raw yasak).
- `cost_method` ilk `stock_valuation_layers` kaydından sonra değiştirilemez (422) — Product model'de zaten var olan `saving` guard'ına eklenir.
- FIFO tüketimi `lockForUpdate()` + tek transaction (gerçek çoklu-process yarışı bu test paketinde simüle edilemez; tek-process ardışık çağrılarla doğruluk test edilir, kod incelemesiyle kilit doğrulanır).
- Yeni metinler `__('EN')` + tr.json.

---

### Task 1: Migration'lar + modeller
- [ ] `stock_valuation_layers` (tenant_id, product_id, stock_move_id, qty, unit_cost, remaining_value)
- [ ] `landed_costs` (split_method, status), `landed_cost_lines` (description, amount), `landed_cost_distributions` (stock_move_id, allocated_amount)
- [ ] `products.weight`/`volume` nullable decimal(15,4) eklenir (by_weight/by_volume için)
- [ ] Modeller + factory'ler + morph alias gerekmiyor (hepsi FK, polymorphic değil)

### Task 2: CostingStrategyInterface + 3 strateji + CostingService
- [ ] `CostingStrategyInterface`: `recordInbound(Product, StockMove, string $qty, string $unitCost): StockValuationLayer`, `consumeOutbound(Product, StockMove, string $qty): string` (COGS değeri döner)
- [ ] `FifoCostingStrategy`: giriş yeni katman; çıkış en eski katmandan `lockForUpdate()` ile tüketim (katman bitince sıradakine geç)
- [ ] `AvcoCostingStrategy`: giriş ağırlıklı ortalamayı yeniden hesaplayıp `products.avco_unit_cost`'a önbellekler + audit satırı; çıkış güncel ortalamayla değerlenir (audit satırı negatif qty)
- [ ] `StandardCostingStrategy`: sabit `products.standard_cost` kullanılır; girişte verilen unitCost farklıysa yok sayılır (sapma muhasebesi Faz 9'a bırakılır, burada yalnızca sabit değer kaydedilir)
- [ ] `CostingService`: `product.cost_method`'a göre doğru stratejiyi seçip delegasyon yapar
- [ ] `Product` model guard: `cost_method` değişimi, o ürüne ait herhangi bir `stock_valuation_layers` kaydı varsa 422
- [ ] Testler: FIFO çok katman tüketimi (10×5+20×5 girişten 7 çıkış → COGS 90); AVCO aynı senaryo → 105; katman tükenince sıradakine geçiş; yetersiz katmanda tüketim davranışı; standard sabit değer; cost_method kilidi

### Task 3: LandedCostService
- [ ] `validate(LandedCost, array $stockMoveIds)`: `landed_cost_lines` toplamını `split_method`'a göre dağıtır (by_quantity: move.qty oranı; by_current_cost: move'un mevcut katman değeri oranı; by_weight/by_volume: `product.weight`/`volume` × qty oranı); `landed_cost_distributions` satırları + ilgili `stock_valuation_layers.unit_cost`/`remaining_value` geriye dönük artırılır
- [ ] Standart maliyetli ürüne ait move hedefse tüm işlem 409 ile reddedilir
- [ ] Onay izni: yalnızca `approve landed costs` (Tenant Admin varsayılan)
- [ ] Testler: 4 dağıtım yöntemi matematiği; standard ürün 409; izinsiz kullanıcı 403; onaylanan fiş tekrar validate edilemez (422)

## Self-Review
- PRD 3.5 "katman tüketimi eşzamanlılığa karşı korumalı" → kod `lockForUpdate()` kullanıyor; gerçek concurrent-process testi bu PHPUnit paketinde mümkün değil, kod incelemesiyle doğrulanacak.
- Motor bilinçli olarak StockMoveService'e otomatik bağlanmadı — transferlerin değer yaratmaması gerektiği için; PO/SO geldiğinde (Faz 7/8) o servisler CostingService'i çağıracak.
- `products.weight/volume` PRD'nin şemasında yok ama `by_weight`/`by_volume` dağıtımı yapısal olarak bu alanları gerektiriyor — makul boşluk doldurma, plana not düşüldü (Faz 2/4'teki `*_lines` tabloları emsaliyle tutarlı).
