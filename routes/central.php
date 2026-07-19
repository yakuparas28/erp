<?php

use App\Http\Controllers\Central\AuthController;
use App\Http\Controllers\Central\ModuleActivationController;
use App\Http\Controllers\Central\SubscriptionController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\Web\LoginController;
use App\Http\Controllers\Central\Web\TenantPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/central')->name('central.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:super_admin')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');

        Route::post('/tenants/{tenant}/subscription', [SubscriptionController::class, 'store'])
            ->name('tenants.subscription.store');

        Route::post('/tenants/{tenant}/modules/{module:key}', [ModuleActivationController::class, 'store'])
            ->withoutScopedBindings()
            ->name('tenants.modules.activate');
        Route::delete('/tenants/{tenant}/modules/{module:key}', [ModuleActivationController::class, 'destroy'])
            ->withoutScopedBindings()
            ->name('tenants.modules.deactivate');
    });
});

Route::middleware('web')->prefix('central')->name('central.web.')->group(function (): void {
    Route::middleware('guest:central_web')->group(function (): void {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:central_web')->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/tenants', [TenantPageController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [TenantPageController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [TenantPageController::class, 'show'])->name('tenants.show');
        Route::put('/tenants/{tenant}', [TenantPageController::class, 'update'])->name('tenants.update');

        Route::post('/tenants/{tenant}/subscription', [TenantPageController::class, 'storeSubscription'])->name('tenants.subscription.store');
        Route::post('/tenants/{tenant}/modules/{module:key}', [TenantPageController::class, 'activateModule'])->withoutScopedBindings()->name('tenants.modules.activate');
        Route::delete('/tenants/{tenant}/modules/{module:key}', [TenantPageController::class, 'deactivateModule'])->withoutScopedBindings()->name('tenants.modules.deactivate');
    });
});
