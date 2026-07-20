<?php

use App\Http\Controllers\Central\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Central\Api\ModuleActivationController as ApiModuleActivationController;
use App\Http\Controllers\Central\Api\SubscriptionController as ApiSubscriptionController;
use App\Http\Controllers\Central\Api\TenantController as ApiTenantController;
use App\Http\Controllers\Central\ImpersonationController;
use App\Http\Controllers\Central\MailSettingController;
use App\Http\Controllers\Central\NotificationTemplateController;
use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Süper Admin API'si (Sanctum, PRD: /api/central/*)
|--------------------------------------------------------------------------
*/
Route::middleware('api')->prefix('api/central')->name('central.')->group(function (): void {
    Route::post('/login', [ApiAuthController::class, 'login'])->name('login');

    Route::middleware('auth:super_admin')->group(function (): void {
        Route::get('/me', [ApiAuthController::class, 'me'])->name('me');

        Route::get('/tenants', [ApiTenantController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [ApiTenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [ApiTenantController::class, 'show'])->name('tenants.show');

        Route::post('/tenants/{tenant}/subscription', [ApiSubscriptionController::class, 'store'])
            ->name('tenants.subscription.store');

        Route::post('/tenants/{tenant}/modules/{module:key}', [ApiModuleActivationController::class, 'store'])
            ->withoutScopedBindings()
            ->name('tenants.modules.activate');
        Route::delete('/tenants/{tenant}/modules/{module:key}', [ApiModuleActivationController::class, 'destroy'])
            ->withoutScopedBindings()
            ->name('tenants.modules.deactivate');
    });
});

/*
|--------------------------------------------------------------------------
| Süper Admin Web Paneli (/central/*)
|--------------------------------------------------------------------------
*/
Route::middleware('web')->prefix('central')->name('central.web.')->group(function (): void {
    Route::get('/login', fn () => redirect()->route('login'))->name('login');

    Route::middleware('auth:central_web')->group(function (): void {
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');

        Route::post('/tenants/{tenant}/subscription', [TenantController::class, 'storeSubscription'])->name('tenants.subscription.store');
        Route::post('/tenants/{tenant}/modules/{module:key}', [TenantController::class, 'activateModule'])->withoutScopedBindings()->name('tenants.modules.activate');
        Route::delete('/tenants/{tenant}/modules/{module:key}', [TenantController::class, 'deactivateModule'])->withoutScopedBindings()->name('tenants.modules.deactivate');
        Route::put('/tenants/{tenant}/mail-settings', [MailSettingController::class, 'updateForTenant'])->name('tenants.mail-settings.update');
        Route::post('/tenants/{tenant}/users/{user}/impersonate', [ImpersonationController::class, 'store'])->withoutScopedBindings()->name('tenants.users.impersonate');

        Route::get('/settings/mail', [MailSettingController::class, 'edit'])->name('settings.mail');
        Route::put('/settings/mail', [MailSettingController::class, 'update'])->name('settings.mail.update');

        Route::get('/settings/notification-templates', [NotificationTemplateController::class, 'index'])->name('settings.templates');
        Route::put('/settings/notification-templates/{template}', [NotificationTemplateController::class, 'update'])->name('settings.templates.update');
    });
});
