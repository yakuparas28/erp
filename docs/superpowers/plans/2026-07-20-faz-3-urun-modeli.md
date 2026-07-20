# Faz 3: Ürün Modeli — Varyant, Kit, Hizmet Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal:** PRD 3.17/4.1.7 — varyantlı ürünler (template+attribute), asorti/kit (bileşen patlaması), hizmet/sarf malzeme kuralları.

**Architecture:** `Modules/Inventory` altında devam (ürün kavramının uzantısı). `products.product_template_id` dolu satırlar varyanttır; `is_kit=true` satırların bileşenleri `product_kit_components`'te. Kit patlaması `KitExplosionService` StockMoveService'i sarmalar (kit'e asla move yazılmaz).

## Global Constraints
- Master plan + Faz 2 kısıtları geçerli.
- Hizmet ürünü yasağı zaten StockMoveService'te var (Faz 2) — kit/varyant onunla çakışmaz.
- Creation mode kilidi ve iç içe kit yasağı PRD'de açık kural; testle kanıtlanacak.

---

### Task 1: Migration'lar + modeller
- [ ] product_templates(base_price), product_attributes(creation_mode), product_attribute_values(price_extra), product_template_attribute_lines, product_variant_attribute_values, product_kit_components
- [ ] Modeller+factory'ler+morph alias yok (polymorphic değil)

### Task 2: VariantGeneratorService
- [ ] `generateInstant(template)`: tüm kombinasyonları queued job ile products satırı üretir (barkod/fiyat bağımsız)
- [ ] `resolveOrCreateDynamic(template, valueIds)`: dynamic modda lazy oluşturma (Faz 8'de SO satırından çağrılacak, burada servis olarak hazırlanır+test edilir)
- [ ] Creation mode kilidi: template_attribute_line eklendikten sonra `creation_mode` değişimi 422
- [ ] Önerilen fiyat: base_price + Σ price_extra

### Task 3: KitExplosionService
- [ ] Kit bileşeni is_kit=true olamaz (422, iç içe kit yasak)
- [ ] Kit'e stock_quant/move asla yazılmaz
- [ ] `explode(kitProduct, qty, locationIds, referenceType, referenceId)`: bileşenlerden biri yetersizse TÜMÜ reddedilir (tek transaction)

### Task 4: Hizmet/sarf kuralları + web ekranı
- [ ] Ürün formuna varyant/kit sekmesi (opsiyonel — minimal: template listesi + attribute yönetimi ekranı)
- [ ] Testler + çeviriler + commit
