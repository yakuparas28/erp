<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Api\SyncController;

/*
|--------------------------------------------------------------------------
| Mobil Senkronizasyon API'si (PRD 3.2–3.3)
|--------------------------------------------------------------------------
| El terminali (React Native/Expo, ayrı depo) bu uçları kullanır. Kimlik
| doğrulama tenant kullanıcısının Sanctum token'ıyla yapılır.
*/
Route::middleware(['auth:sanctum'])->prefix('inventory/sync')->name('inventory.sync.')->group(function (): void {
    Route::middleware('permission:view stock')->group(function (): void {
        Route::get('/catalog', [SyncController::class, 'catalog'])->name('catalog');
    });

    Route::middleware('permission:perform stock counts')->group(function (): void {
        Route::post('/counts', [SyncController::class, 'pushCounts'])->name('counts.store');
    });
});
