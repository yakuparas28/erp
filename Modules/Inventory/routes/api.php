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
Route::middleware(['auth:sanctum', 'permission:view stock'])->prefix('inventory/sync')->name('inventory.sync.')->group(function (): void {
    Route::get('/catalog', [SyncController::class, 'catalog'])->name('catalog');
});
