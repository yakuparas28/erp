<?php

use App\Http\Controllers\Central\AuthController;
use App\Http\Controllers\Central\ModuleActivationController;
use App\Http\Controllers\Central\SubscriptionController;
use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/central')->name('central.')->group(function (): void {
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
