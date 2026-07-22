<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesOrderController;

Route::middleware(['auth:web'])->prefix('app/sales')->name('app.sales.')->group(function (): void {
    Route::middleware('permission:create sales orders,web')->group(function (): void {
        Route::get('/orders', [SalesOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [SalesOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{so}', [SalesOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{so}/lines', [SalesOrderController::class, 'storeLine'])->name('orders.lines.store');
        Route::post('/orders/{so}/send-quotation', [SalesOrderController::class, 'sendQuotation'])->name('orders.send-quotation');
        Route::post('/orders/{so}/cancel', [SalesOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/lines/{line}/deliver', [SalesOrderController::class, 'deliver'])->name('lines.deliver');
    });

    Route::middleware('permission:confirm sales orders,web')->group(function (): void {
        Route::post('/orders/{so}/confirm', [SalesOrderController::class, 'confirm'])->name('orders.confirm');
    });
});
