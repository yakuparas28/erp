<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\AdjustmentController;
use Modules\Inventory\Http\Controllers\KitComponentController;
use Modules\Inventory\Http\Controllers\ProductController;
use Modules\Inventory\Http\Controllers\ProductTemplateController;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\TransferController;
use Modules\Inventory\Http\Controllers\WarehouseController;

Route::middleware(['auth:web'])->prefix('app/inventory')->name('app.inventory.')->group(function (): void {
    Route::middleware('permission:manage products,web')->group(function (): void {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/product-categories', [ProductController::class, 'storeCategory'])->name('categories.store');
        Route::post('/products/{product}/kit-components', [KitComponentController::class, 'store'])->name('kit-components.store');
        Route::delete('/kit-components/{component}', [KitComponentController::class, 'destroy'])->name('kit-components.destroy');

        Route::get('/templates', [ProductTemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [ProductTemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}', [ProductTemplateController::class, 'show'])->name('templates.show');
        Route::delete('/templates/{template}', [ProductTemplateController::class, 'destroy'])->name('templates.destroy');
        Route::post('/attributes', [ProductTemplateController::class, 'storeAttribute'])->name('attributes.store');
        Route::delete('/attributes/{attribute}', [ProductTemplateController::class, 'destroyAttribute'])->name('attributes.destroy');
        Route::post('/attributes/{attribute}/values', [ProductTemplateController::class, 'storeAttributeValue'])->name('attributes.values.store');
        Route::delete('/attribute-values/{value}', [ProductTemplateController::class, 'destroyAttributeValue'])->name('attribute-values.destroy');
        Route::post('/templates/{template}/attributes', [ProductTemplateController::class, 'attachAttribute'])->name('templates.attributes.attach');
    });

    Route::middleware('permission:manage warehouses,web')->group(function (): void {
        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::post('/locations', [WarehouseController::class, 'storeLocation'])->name('locations.store');
    });

    Route::middleware('permission:view stock,web')->group(function (): void {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    });

    Route::middleware('permission:perform stock counts,web')->group(function (): void {
        Route::get('/adjustments', [AdjustmentController::class, 'index'])->name('adjustments.index');
        Route::post('/adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('/adjustments/{adjustment}', [AdjustmentController::class, 'show'])->name('adjustments.show');
        Route::post('/adjustments/{adjustment}/counts', [AdjustmentController::class, 'addCount'])->name('adjustments.counts.store');
        Route::post('/adjustments/{adjustment}/submit', [AdjustmentController::class, 'submit'])->name('adjustments.submit');
        Route::post('/adjustments/{adjustment}/cancel', [AdjustmentController::class, 'cancel'])->name('adjustments.cancel');
    });

    // Onay: servis katmanı izni + görev ayrılığını ayrıca doğrular
    Route::middleware('permission:approve inventory adjustments,web')->group(function (): void {
        Route::post('/adjustments/{adjustment}/approve', [AdjustmentController::class, 'approve'])->name('adjustments.approve');
    });

    Route::middleware('permission:manage warehouse transfers,web')->group(function (): void {
        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('/transfers/{transfer}/complete', [TransferController::class, 'complete'])->name('transfers.complete');
    });
});
