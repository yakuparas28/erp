# Faz 1b: Süper Admin Web Paneli (Dreams ERP Şablonu)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Steps use checkbox syntax.

**Goal:** Faz 1 platform CRUD'u (tenant, abonelik, modül aktivasyonu) için `public/template/v1` (Dreams ERP, Tailwind v4 + Preline) şablonuyla Süper Admin web paneli.

**Architecture:** Session tabanlı `central_web` guard'ı (`super_admins` provider). Route'lar `routes/central.php` içine `web` middleware grubuyla eklenir (`/central/*`). Blade layout'ları şablonun `blank-page.html` iskeletinden; asset'ler `asset('template/v1/assets/...')` ile doğrudan public'ten. UI dili Türkçe. Mevcut `ModuleActivationService` yeniden kullanılır — iş mantığı çoğaltılmaz.

**Tech Stack:** Blade, Tailwind v4 (şablonun derlenmiş style.css'i), Preline (modal/dropdown), Phosphor ikonlar.

## Global Constraints
- Master plan kuralları geçerli. Activity log tüm mutasyonlarda sürer (web'de causer = `auth('central_web')`).
- Şablonun 39M'lık libs klasöründen yalnız kullanılan dosyalar referans alınır (phosphor, simplebar, preline, theme-script, style.css).
- Testler PHPUnit; her görev sonunda pint + ilgili testler + commit.

### Task 1: central_web guard + web login/logout
- `config/auth.php`: `central_web` guard (session, super_admins)
- `app/Http/Controllers/Central/Web/LoginController.php` (showLoginForm/login/logout)
- `routes/central.php`: `Route::middleware('web')->prefix('central')` grubu — login GET/POST, logout POST, korumalı grup `auth:central_web`
- Test: `tests/Feature/Central/Web/LoginTest.php` — login sayfası 200; doğru şifre → redirect /central/tenants + authenticated; yanlış → hata; logout; korumasız erişim → login'e redirect

### Task 2: Blade layout'lar
- `resources/views/central/layouts/guest.blade.php` (login.html'den)
- `resources/views/central/layouts/app.blade.php` + `partials/sidebar.blade.php`, `partials/header.blade.php` (blank-page.html iskeleti; minimal Türkçe menü: Tenant'lar)
- Test: login sayfası şablon asset'lerini içerir (assertSee style.css)

### Task 3: Tenant listesi CRUD
- `app/Http/Controllers/Central/Web/TenantPageController.php` (index/store/update/destroy? — v1'de silme YOK, tenant silmek yıkıcı; yalnız oluştur+düzenle)
- View: `central/tenants/index.blade.php` — user-management.html tablo + add/edit modal kalıbı; alanlar: ad, muhasebe modu, abonelik paketi (badge), oluşturma tarihi
- Activity log: tenant.created / tenant.updated
- Test: index listeler; store oluşturur+redirect+log; update günceller

### Task 4: Tenant detay — abonelik + modüller + log
- `central/tenants/show.blade.php`: abonelik atama formu (paket seç + durum + başlangıç), modül toggle listesi (core kilitli), son activity kayıtları
- Controller aksiyonları: subscription store (upsert, observer senkronu), module activate/deactivate (`ModuleActivationService`)
- Test: abonelik POST → aktivasyonlar; modül toggle; core kapatma 422/redirect hata

## Self-Review
- API endpoint'leri (Sanctum) aynen kalır — panel bunların yanına eklenir, yerine geçmez.
- Tenant silme bilinçli kapsam dışı (yıkıcı; PRD'de tanımsız).
