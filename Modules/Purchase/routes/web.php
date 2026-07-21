<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\PurchaseOrderController;

Route::middleware(['auth:web'])->prefix('app/purchase')->name('app.purchase.')->group(function (): void {
    Route::middleware('permission:create purchase orders,web')->group(function (): void {
        Route::get('/orders', [PurchaseOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{po}', [PurchaseOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{po}/lines', [PurchaseOrderController::class, 'storeLine'])->name('orders.lines.store');
        Route::post('/orders/{po}/send-rfq', [PurchaseOrderController::class, 'sendRfq'])->name('orders.send-rfq');
        Route::post('/orders/{po}/cancel', [PurchaseOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/lines/{line}/receive', [PurchaseOrderController::class, 'receive'])->name('lines.receive');
    });

    Route::middleware('permission:confirm purchase orders,web')->group(function (): void {
        Route::post('/orders/{po}/confirm', [PurchaseOrderController::class, 'confirm'])->name('orders.confirm');
    });
});
