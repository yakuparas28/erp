# Faz 1: Platform Yönetimi — Lisans Paketleri ve Modül Aktivasyonu

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tenant'ların modül erişimini lisans paketine bağlayan platform katmanı: modül kaydı, paketler, abonelikler, aktivasyonlar, `EnsureModuleActive` middleware ve Süper Admin CRUD API'leri (activity log ile).

**Architecture:** Platform tabloları (`modules`, `license_packages`, `license_package_modules`) tenant'sızdır; `tenant_subscriptions`/`tenant_module_activations` tenant_id taşır ama **BelongsToTenant KULLANMAZ** — platform yönetimli tablolardır, sorgular explicit tenant_id ile yapılır (middleware tenant isteği içinde de çalışır, scope çakışması istemeyiz). Abonelik→aktivasyon yansıması Observer + Service ile; manuel addon'lar paket senkronundan etkilenmez.

**Tech Stack:** Faz 0 temeli + spatie/laravel-activitylog (`activity()` helper).

## Global Constraints

- Master plan Global Constraints geçerli. PRD 4.1.6 şemaları birebir.
- `EnsureModuleActive` alias'ı `module`; Inventory `is_core=true` → middleware'de daima geçer; kapalı modül → 403 `"Bu modül paketinizde aktif değil"`.
- Tüm süper admin aksiyonları (tenant oluşturma, abonelik atama, modül aç/kapa) activity_log'a causer=SuperAdmin ile yazılır.

---

### Task 1: Migration'lar + modeller + seeder'lar

**Files:**
- Create: migrations (5), `app/Models/Module.php`, `app/Models/LicensePackage.php`, `app/Models/TenantSubscription.php`, `app/Models/TenantModuleActivation.php`, factories (4), `database/seeders/ModuleSeeder.php`, `database/seeders/LicensePackageSeeder.php`
- Test: `tests/Feature/Central/PlatformCatalogTest.php`

**Interfaces (Produces):**
- `Module` (key unique, name, description, is_core bool) — `LicensePackage::modules()` belongsToMany (pivot `license_package_modules`), `Module::isCore()`
- `TenantSubscription` (tenant_id, license_package_id, status enum trial/active/past_due/cancelled, starts_at, ends_at) — `licensePackage()` BelongsTo
- `TenantModuleActivation` (tenant_id, module_id, is_active, source enum package/manual_addon/trial, activated_by_super_admin_id, activated_at, deactivated_at; unique tenant+module)
- Seed: inventory(is_core)/sales/purchase/accounting; paketler: Başlangıç(inventory), Standart(+sales,purchase), Premium(hepsi)

Test senaryoları: seed sonrası 4 modül (inventory is_core); 3 paket doğru modül setleriyle.

- [ ] **Step 1:** Failing test → **Step 2:** implement → **Step 3:** `--filter=PlatformCatalogTest` PASS + pint + commit

### Task 2: Abonelik → aktivasyon senkronu (Observer + Service)

**Files:**
- Create: `app/Services/Platform/ModuleActivationService.php`, `app/Observers/TenantSubscriptionObserver.php`
- Modify: `app/Models/TenantSubscription.php` (`#[ObservedBy]`)
- Test: `tests/Feature/Central/SubscriptionActivationSyncTest.php`

**Interfaces (Produces):** `ModuleActivationService::syncFromPackage(TenantSubscription $subscription): void` — paket modüllerini `source=package` upsert eder (is_active=true, activated_at=now); pakette olmayan eski `source=package` kayıtlarını `is_active=false, deactivated_at=now` yapar; `source=manual_addon` kayıtlara DOKUNMAZ. `activateManually(Tenant, Module, SuperAdmin): TenantModuleActivation` ve `deactivate(Tenant, Module, SuperAdmin): void` (is_core → InvalidArgumentException).

Test senaryoları: (1) abonelik oluşturunca paket modülleri aktive; (2) paket değişince (update) yeni modül aktif, çıkan modül pasif; (3) manuel addon paket senkronunda korunur; (4) is_core modül deactivate edilemez.

- [ ] **Step 1:** Failing test → **Step 2:** implement → **Step 3:** PASS + pint + commit

### Task 3: EnsureModuleActive middleware

**Files:**
- Create: `app/Http/Middleware/EnsureModuleActive.php`
- Modify: `bootstrap/app.php` (alias `module`), `Modules/{Sales,Purchase,Accounting}/routes/api.php` (grup middleware `['auth:sanctum','module:{key}']`)
- Test: `tests/Feature/EnsureModuleActiveTest.php` (test içinde kayıtlı fixture route'lar)

```php
public function handle(Request $request, Closure $next, string $moduleKey): Response
{
    $module = Module::where('key', $moduleKey)->first();
    abort_if($module === null, 500, "Tanımsız modül anahtarı: {$moduleKey}");
    if ($module->is_core) { return $next($request); }
    $tenantId = $request->user()?->getAttribute('tenant_id');
    $isActive = $tenantId !== null && TenantModuleActivation::where('tenant_id', $tenantId)
        ->where('module_id', $module->id)->where('is_active', true)->exists();
    abort_unless($isActive, 403, 'Bu modül paketinizde aktif değil');
    return $next($request);
}
```

Test senaryoları: (1) aktivasyonsuz tenant `module:accounting` → 403 + mesaj; (2) aktivasyonlu → 200; (3) `module:inventory` aktivasyonsuz → 200 (core); (4) pasifleştirilmiş (is_active=false) → 403.

- [ ] **Step 1:** Failing test → **Step 2:** implement + alias + modül route grupları → **Step 3:** PASS + pint + commit

### Task 4: Süper Admin CRUD API + activity log

**Files:**
- Create: `app/Http/Controllers/Central/TenantController.php`, `app/Http/Controllers/Central/SubscriptionController.php`, `app/Http/Controllers/Central/ModuleActivationController.php`, FormRequest'ler (`app/Http/Requests/Central/StoreTenantRequest.php`, `StoreSubscriptionRequest.php`)
- Modify: `routes/central.php`
- Test: `tests/Feature/Central/CentralApiTest.php`

**Interfaces (Produces):** `auth:super_admin` arkasında:
- `GET/POST /api/central/tenants` (+ `GET /api/central/tenants/{tenant}`)
- `POST /api/central/tenants/{tenant}/subscription` {license_package_id, status, starts_at, ends_at?} → upsert abonelik (observer senkronu tetikler)
- `POST /api/central/tenants/{tenant}/modules/{module:key}` → manual_addon aktivasyon; `DELETE` → deaktivasyon (is_core 422)
- Her mutasyon `activity()->causedBy($superAdmin)->performedOn(...)->log('...')`

Test senaryoları: (1) tenant oluşturma 201 + activity_log kaydı (causer super admin); (2) abonelik atama → aktivasyonlar oluşur + log; (3) manual addon POST → aktif + log; DELETE core → 422; (4) tenant kullanıcısı token'ı ile 401.

- [ ] **Step 1:** Failing test → **Step 2:** implement → **Step 3:** PASS + pint + tüm suite + commit

## Self-Review
- PRD 3.16 tüm kuralları karşılanıyor: package/manual_addon/trial source'ları (trial source'u abonelik status=trial iken senkronda kullanılır), core kilidi, 403 mesajı, activity log.
- Modül route'ları artık merkezi middleware arkasında (PRD 4.2 'Modül Erişim Zorunluluğu') — controller içinde ayrıca kontrol YOK.
- Senkron davranışı: subscription `status=cancelled/past_due` olduğunda aktivasyonların düşürülmesi bilinçli olarak v1'de senkron dışı bırakıldı (PRD bunu açıkça tanımlamıyor); yalnız paket içeriği senkronlanır. Not: cancelled abonelikte erişim kesme kuralı Faz 1 sonrası netleştirilecek — plan notu.
