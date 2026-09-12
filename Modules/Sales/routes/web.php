<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\DeliveryCarrierController;
use Modules\Sales\Http\Controllers\QuotationController;
use Modules\Sales\Http\Controllers\SalesOrderController;

Route::middleware(['auth:web', 'module:sales'])->prefix('app/sales')->name('app.sales.')->group(function (): void {
    Route::middleware('permission:create sales orders,web')->group(function (): void {
        Route::get('/carriers', [DeliveryCarrierController::class, 'index'])->name('carriers.index');
        Route::post('/carriers', [DeliveryCarrierController::class, 'store'])->name('carriers.store');
        Route::patch('/carriers/{carrier}', [DeliveryCarrierController::class, 'update'])->name('carriers.update');
        Route::delete('/carriers/{carrier}', [DeliveryCarrierController::class, 'destroy'])->name('carriers.destroy');

        Route::get('/orders', [SalesOrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [SalesOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{so}', [SalesOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{so}/lines', [SalesOrderController::class, 'storeLine'])->name('orders.lines.store');
        Route::post('/orders/{so}/lines/configure', [SalesOrderController::class, 'configureAndAddLine'])->name('orders.lines.configure');
        Route::post('/orders/{so}/send-quotation', [SalesOrderController::class, 'sendQuotation'])->name('orders.send-quotation');
        Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
        Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
        Route::get('/orders/{so}/quotation', [SalesOrderController::class, 'quotation'])->name('orders.quotation');
        Route::post('/orders/{so}/cancel', [SalesOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/lines/{line}/deliver', [SalesOrderController::class, 'deliver'])->name('lines.deliver');
        Route::post('/lines/{line}/return', [SalesOrderController::class, 'returnDelivery'])->name('lines.return');
        Route::post('/lines/{line}/reserve', [SalesOrderController::class, 'reserveLine'])->name('lines.reserve');
        Route::post('/lines/{line}/unreserve', [SalesOrderController::class, 'unreserveLine'])->name('lines.unreserve');
        Route::get('/orders/{so}/delivery-slip', [SalesOrderController::class, 'deliverySlip'])->name('orders.delivery-slip');
    });

    Route::middleware('permission:confirm sales orders,web')->group(function (): void {
        Route::post('/orders/{so}/confirm', [SalesOrderController::class, 'confirm'])->name('orders.confirm');
    });
});
