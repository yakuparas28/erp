# Faz 4: Senkronizasyon Motoru + Mobil API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal:** PRD 3.2–3.3 — El terminali (React Native/Expo, ayrı depo) için sunucu tarafı REST API: tam yerel katalog için delta sync (opak sunucu cursor'ı) ve offline sayım sepetinin idempotent, chunked bulk push'u. Bu depo yalnızca sunucu ucunu kapsar; mobil istemci başka bir depoda.

**Architecture:** `Modules/Inventory/routes/api.php` altında, Sanctum `auth:sanctum` (tenant `User` token'ı — Warehouse Operator rolü push+katalog çekmekle sınırlı, izin bazlı). Cursor tamamen opak ve sunucu üretimli: `base64(json_encode(['t' => <en son updated_at>, 'id' => <en son id>]))` — cihaz saatine asla güvenilmez. Bulk push, `sync_batches` (Faz 2'de PRD şeması zaten var mı? HAYIR — Faz 2 planında yoktu, bu fazda eklenir) ile idempotent; additive birleştirme mevcut `InventoryAdjustmentService::addCount` (Faz 2, zaten additive) yeniden kullanılır.

## Global Constraints
- Master plan Global Constraints geçerli. `sync_batches` PRD 4.1.2 şemasına birebir: `unique(['tenant_id','batch_uuid'])`, kontrol+yazma TEK transaction.
- Leftover scaffold `Modules/Inventory/routes/api.php` + ölü `InventoryController` referansı bu fazda temizlenir (dosya yok, route kırık duruyordu).
- Yeni endpoint metinleri `__('EN')` + tr.json (JSON API'de kullanıcıya dönük metin az ama hata mesajları için geçerli).

---

### Task 1: sync_batches migration + model
- [ ] Migration (PRD 4.1.2 şeması), model+factory, morph alias gerekmiyor (polymorphic değil)

### Task 2: Delta Sync endpoint
- [ ] `GET /api/inventory/sync/catalog?cursor=...` — `auth:sanctum` + `permission:view stock` (Warehouse Operator'da var)
- [ ] `SyncCursorService`: encode/decode (base64 json), `resolve(?string $cursor): array{products, barcodes, uoms, nextCursor}` — `updated_at > cursor.t OR (updated_at = cursor.t AND id > cursor.id)` deseni (aynı saniye içi kayıp riskini önler)
- [ ] Cursor boşsa (ilk indirme) tüm katalog döner
- [ ] Testler: cursor'suz tam katalog; cursor'lu yalnız değişenler; aynı saniyede eklenen ikinci kayıt kaçmıyor; yeni cursor bir sonraki istekte doğru devam noktası

### Task 3: Bulk Push endpoint (idempotent, additive, chunked)
- [ ] `POST /api/inventory/sync/counts` — body: `{batch_uuid, inventory_adjustment_id, lines: [{product_id, qty, lot_id?}], max 1000 lines}`
- [ ] `SyncBatchService::processBulkPush(...)`: `sync_batches` kaydı + tüm satırların `InventoryAdjustmentService::addCount` çağrısı TEK transaction; `batch_uuid` zaten varsa → idempotent 200 (hiçbir yazma tekrarlanmaz)
- [ ] 1000 satır sınırı aşılırsa 422
- [ ] Barkod→ürün+birim çözümleme sunucuda tekrar doğrulanır (`ProductBarcode` + `UomConversionService`) — istemci güvenilmez
- [ ] Testler: aynı UUID 3 kez → tek yazım; iki farklı chunk (UUID'leri farklı) toplamsal birikir; paralel iki "işçi" isteği additive birleşir (mevcut addCount zaten additive); 1001 satır 422; geçersiz barkod 422

### Task 4: Rol kısıtı + route temizliği
- [ ] Scaffold `InventoryController`/`inventories` route kalıntısını sil, gerçek `api.php` ile değiştir
- [ ] Warehouse Operator: yalnızca bu iki endpoint + kendi push'u; `manage products` vb. yetkisi yok (zaten mevcut PermissionCatalog ile tutarlı)
- [ ] Tüm suite + commit

## Self-Review
- PRD 3.3 "sunucu cursor'ı, cihaz saatinden bağımsız" → cursor içeriği DB `updated_at`+`id` çifti, cihazdan gelen hiçbir zaman damgası kullanılmaz.
- Mobil istemcinin kendisi (React Native/Expo/SQLite) bu planın KAPSAMI DIŞINDA — yalnızca sunucu sözleşmesi (endpoint davranışı) test edilir.
