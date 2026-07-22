<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\AdjustmentController;
use Modules\Inventory\Http\Controllers\KitComponentController;
use Modules\Inventory\Http\Controllers\PartnerController;
use Modules\Inventory\Http\Controllers\ProductController;
use Modules\Inventory\Http\Controllers\ProductTemplateController;
use Modules\Inventory\Http\Controllers\PutawayRuleController;
use Modules\Inventory\Http\Controllers\ReorderingRuleController;
use Modules\Inventory\Http\Controllers\RouteController;
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

    Route::middleware('permission:manage partners,web')->group(function (): void {
        Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
        Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
    });

    Route::middleware('permission:manage routes,web')->group(function (): void {
        Route::get('/putaway', [PutawayRuleController::class, 'index'])->name('putaway.index');
        Route::post('/putaway', [PutawayRuleController::class, 'store'])->name('putaway.store');
        Route::delete('/putaway/{rule}', [PutawayRuleController::class, 'destroy'])->name('putaway.destroy');

        Route::get('/routes', [RouteController::class, 'index'])->name('routes.index');
        Route::post('/routes', [RouteController::class, 'store'])->name('routes.store');
        Route::get('/routes/{route}', [RouteController::class, 'show'])->name('routes.show');
        Route::post('/routes/{route}/rules', [RouteController::class, 'storeRule'])->name('routes.rules.store');
        Route::post('/routes/{route}/execute', [RouteController::class, 'execute'])->name('routes.execute');
    });

    Route::middleware('permission:manage reordering rules,web')->group(function (): void {
        Route::get('/reordering', [ReorderingRuleController::class, 'index'])->name('reordering.index');
        Route::post('/reordering', [ReorderingRuleController::class, 'store'])->name('reordering.store');
        Route::delete('/reordering/{rule}', [ReorderingRuleController::class, 'destroy'])->name('reordering.destroy');
        Route::post('/reordering/suggestions/{suggestion}/acknowledge', [ReorderingRuleController::class, 'acknowledge'])->name('reordering.suggestions.acknowledge');
    });
});
