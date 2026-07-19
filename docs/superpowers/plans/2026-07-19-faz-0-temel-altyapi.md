# Faz 0: Temel Altyapı (Foundation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Multi-tenancy çekirdeği (tenants + BelongsToTenant), Sanctum guard'ları (tenant + super_admin), Spatie Permission (Teams), nWidart modül iskeletleri ve test altyapısını kurmak.

**Architecture:** Single-database multi-tenancy. `TenantScope` global scope yalnızca HTTP yaşam döngüsünde auth kullanıcısının `tenant_id`'sine dayanır; SuperAdmin modelinde `tenant_id` olmadığı için scope ona hiç uygulanmaz. Partner guard Faz 12'ye ertelendi (tablosu yok).

**Tech Stack:** Laravel 13, MySQL 8.4 (Homebrew, DB: `erp`), laravel/sanctum v4 (`install:api`), spatie/laravel-permission v6 (teams=tenant_id), spatie/laravel-activitylog v4, nwidart/laravel-modules v13, PHPUnit 12.

## Global Constraints

- Master plandaki tüm Global Constraints geçerli (`2026-07-19-erp-cekirdek-master-plan.md`).
- PRD tablo şemaları birebir izlenir: `unsignedBigInteger('tenant_id')` + index (FK constraint eklenmez — PRD böyle tanımlıyor).
- Her PHP değişikliği sonrası `vendor/bin/pint --dirty --format agent`; her görev sonunda ilgili testler + commit.
- Test DB: MySQL yerine test ortamında SQLite `:memory:` (phpunit.xml zaten ayarlı) — migration'lar iki motorda da çalışmalı.

---

### Task 1: Git deposu + ilk commit

**Files:** yok (git init)

- [ ] **Step 1:** `git init -b main && git add -A && git commit -m "chore: initial Laravel 13 skeleton + master plan"`

### Task 2: Paket kurulumları

**Files:**
- Modify: `composer.json`, `config/` (publish'ler), `bootstrap/providers.php`

- [ ] **Step 1:** `php artisan install:api --no-interaction` (Sanctum + routes/api.php + personal_access_tokens migration)
- [ ] **Step 2:** `composer require spatie/laravel-permission spatie/laravel-activitylog nwidart/laravel-modules --no-interaction`
- [ ] **Step 3:** Publish: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction` ve activitylog migration'ları + `vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider" --no-interaction`
- [ ] **Step 4:** `composer.json` autoload'a `"Modules\\": "Modules/"` psr-4 ekle + `composer dump-autoload`
- [ ] **Step 5:** `php artisan migrate --no-interaction` + testler yeşil + commit

### Task 3: tenants tablosu + Tenant modeli + users.tenant_id

**Files:**
- Create: `app/Models/Tenant.php`, `database/factories/TenantFactory.php`, migration `create_tenants_table`, migration `add_tenant_id_to_users_table`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/TenantModelTest.php`

**Interfaces (Produces):** `Tenant` modeli (`name`, `accounting_mode` enum anglo_saxon|continental, default continental); `User::tenant()` BelongsTo; `TenantFactory`, `UserFactory->for($tenant)`.

Migration (tenants):
```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('accounting_mode', ['anglo_saxon', 'continental'])->default('continental');
    $table->timestamps();
});
```
Migration (users):
```php
Schema::table('users', function (Blueprint $table) {
    $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
    $table->index('tenant_id');
});
```

- [ ] **Step 1:** Failing test: tenant oluşturulabilir, user tenant'a bağlanır (`$user->tenant->is($tenant)`)
- [ ] **Step 2:** Migration + model + factory yaz; User'a `tenant_id` fillable değil (guarded), `tenant()` ilişkisi
- [ ] **Step 3:** `php artisan test --compact --filter=TenantModelTest` PASS + pint + commit

### Task 4: BelongsToTenant trait + TenantScope

**Files:**
- Create: `app/Models/Scopes/TenantScope.php`, `app/Models/Concerns/BelongsToTenant.php`
- Test: `tests/Feature/BelongsToTenantTest.php` (test-only tablo `tenant_test_items` Schema ile testte kurulur)

**Interfaces (Produces):** `BelongsToTenant` trait — sonraki TÜM tenant modelleri kullanır. Scope: auth kullanıcısının `tenant_id`'si doluysa `WHERE {table}.tenant_id = ?`; creating'de otomatik doldurma. `withoutGlobalScope(TenantScope::class)` super admin explicit erişimi için.

```php
// app/Models/Scopes/TenantScope.php
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = auth()->hasUser() ? auth()->user()->getAttribute('tenant_id') : null;
        if ($tenantId !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }
}
// app/Models/Concerns/BelongsToTenant.php
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null
                && auth()->hasUser()
                && auth()->user()->getAttribute('tenant_id') !== null) {
                $model->setAttribute('tenant_id', auth()->user()->getAttribute('tenant_id'));
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

Test senaryoları: (1) tenant A kullanıcısı yalnız kendi kayıtlarını görür; (2) creating otomatik tenant_id doldurur; (3) auth yokken scope uygulanmaz (job/console senaryosu — explicit tenant_id şart); (4) `withoutGlobalScope` tümünü görür.

- [ ] **Step 1:** Failing test yaz (RefreshDatabase; setUp'ta `Schema::create('tenant_test_items', ...)`; inline `TenantTestItem` modeli trait'li)
- [ ] **Step 2:** Scope + trait implement et
- [ ] **Step 3:** `--filter=BelongsToTenantTest` PASS + pint + commit

### Task 5: Spatie Permission Teams + roller

**Files:**
- Modify: `config/permission.php` (`'teams' => true`, `'team_foreign_key' => 'tenant_id'`), `app/Models/User.php` (HasRoles), `bootstrap/app.php` (middleware alias + append)
- Create: `app/Http/Middleware/SetPermissionsTeamId.php`, `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/RolePermissionTest.php`

**Interfaces (Produces):** Roller: `Tenant Admin`, `Warehouse Operator` (global, team_id null — tenant bazlı atama `setPermissionsTeamId` ile). Middleware her istekte `setPermissionsTeamId(auth()->user()?->tenant_id)`.

```php
// app/Http/Middleware/SetPermissionsTeamId.php
public function handle(Request $request, Closure $next): Response
{
    if (auth()->hasUser() && auth()->user()->getAttribute('tenant_id') !== null) {
        setPermissionsTeamId(auth()->user()->getAttribute('tenant_id'));
    }
    return $next($request);
}
```

- [ ] **Step 1:** Failing test: tenant A'da 'Tenant Admin' atanan kullanıcı, team context A'da `hasRole` true, team context B'de false
- [ ] **Step 2:** Config + middleware + seeder implement; permission tabloları migrate
- [ ] **Step 3:** `--filter=RolePermissionTest` PASS + pint + commit

### Task 6: SuperAdmin + super_admin guard + /api/central iskeleti

**Files:**
- Create: migration `create_super_admins_table` (PRD 4.1.6 birebir), `app/Models/SuperAdmin.php`, `database/factories/SuperAdminFactory.php`, `app/Http/Controllers/Central/AuthController.php`, `routes/central.php`
- Modify: `config/auth.php` (guard+provider), `bootstrap/app.php` (central route dosyası)
- Test: `tests/Feature/Central/SuperAdminAuthTest.php`

**Interfaces (Produces):** `SuperAdmin` (Authenticatable + HasApiTokens, tenant_id YOK); guard `super_admin` (driver sanctum, provider super_admins); route grubu `/api/central/*`; `POST /api/central/login` → token, `GET /api/central/me` (auth:super_admin).

```php
// config/auth.php eklemeleri
'guards' => [
    // ...
    'super_admin' => ['driver' => 'sanctum', 'provider' => 'super_admins'],
],
'providers' => [
    // ...
    'super_admins' => ['driver' => 'eloquent', 'model' => App\Models\SuperAdmin::class],
],
```

Test senaryoları: (1) login doğru şifreyle token döner; (2) token ile /me 200 ve email doğru; (3) tokensız 401; (4) tenant kullanıcısının sanctum token'ı central route'ta 401.

- [ ] **Step 1:** Failing testler
- [ ] **Step 2:** Migration + model + guard + controller + route implement
- [ ] **Step 3:** `--filter=SuperAdminAuthTest` PASS + pint + commit

### Task 7: nWidart modül iskeletleri + morphMap

**Files:**
- Create: `Modules/Inventory`, `Modules/Sales`, `Modules/Purchase`, `Modules/Accounting` (module:make), her `module.json`'da `requires`
- Modify: `app/Providers/AppServiceProvider.php` (morphMap başlangıcı)
- Test: `tests/Feature/ModuleScaffoldTest.php`

**Interfaces (Produces):** Modül anahtarları: `inventory` (core), `sales`, `purchase`, `accounting`. `Relation::enforceMorphMap([...])` merkezi kaydı (başlangıçta boş harita değil — `tenant` gibi ilk alias'larla; her faz kendi alias'larını ekler).

- [ ] **Step 1:** `php artisan module:make Inventory Sales Purchase Accounting --no-interaction`
- [ ] **Step 2:** `module.json` requires düzenle: Sales/Purchase → ["Inventory"], Accounting → ["Inventory"]
- [ ] **Step 3:** AppServiceProvider'a `Relation::enforceMorphMap([])` iskeleti (Faz 2'de alias'lar eklenecek)
- [ ] **Step 4:** Test: 4 modül aktif (`Module::allEnabled()`), route'ları yükleniyor; PASS + pint + commit

### Task 8: TenantTestCase + uçtan uca izolasyon testi

**Files:**
- Create: `tests/TenantTestCase.php`
- Test: `tests/Feature/TenantIsolationTest.php`

**Interfaces (Produces):** `TenantTestCase extends TestCase` — `protected Tenant $tenant; protected User $tenantAdmin; protected function actingAsTenantUser(?Tenant $tenant = null): User` helper'ları. Sonraki tüm fazların feature testleri bunu extend eder.

- [ ] **Step 1:** TenantTestCase yaz (setUp: tenant + admin user + actingAs helper)
- [ ] **Step 2:** İzolasyon testi: iki tenant, kullanıcı listesi sorgusu yalnız kendi tenant'ını döner
- [ ] **Step 3:** Tüm suite `php artisan test --compact` YEŞİL + pint + commit

## Self-Review

- PRD 4.2 (BelongsToTenant, job'larda explicit tenant_id) Task 4 test #3 ile karşılanıyor; PRD 4.1.6 super_admins şeması Task 6'da birebir.
- Partner guard bilinçli olarak Faz 12'ye bırakıldı (model/tablo olmadan guard tanımı ölü konfig olur).
- `EnsureModuleActive` Faz 1'in işi (tablolar orada) — bu fazda değil.
- Tip tutarlılığı: her yerde `getAttribute('tenant_id')` kullanımı SuperAdmin'de kolon olmamasını güvenle ele alır.
