<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\AdjustmentController;
use Modules\Inventory\Http\Controllers\AttributeController;
use Modules\Inventory\Http\Controllers\BarcodeController;
use Modules\Inventory\Http\Controllers\CategoryController;
use Modules\Inventory\Http\Controllers\KitComponentController;
use Modules\Inventory\Http\Controllers\LandedCostController;
use Modules\Inventory\Http\Controllers\LotController;
use Modules\Inventory\Http\Controllers\OperationTypeController;
use Modules\Inventory\Http\Controllers\PackageTypeController;
use Modules\Inventory\Http\Controllers\PartnerController;
use Modules\Inventory\Http\Controllers\ProductController;
use Modules\Inventory\Http\Controllers\ProductTemplateController;
use Modules\Inventory\Http\Controllers\PutawayRuleController;
use Modules\Inventory\Http\Controllers\ReorderingRuleController;
use Modules\Inventory\Http\Controllers\ReportController;
use Modules\Inventory\Http\Controllers\RouteController;
use Modules\Inventory\Http\Controllers\ScrapController;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\StorageCategoryController;
use Modules\Inventory\Http\Controllers\TransferBatchController;
use Modules\Inventory\Http\Controllers\TransferController;
use Modules\Inventory\Http\Controllers\UomController;
use Modules\Inventory\Http\Controllers\WarehouseController;

Route::middleware(['auth:web'])->prefix('app/inventory')->name('app.inventory.')->group(function (): void {
    Route::middleware('permission:manage products,web')->group(function (): void {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/image', [ProductController::class, 'uploadImage'])->name('products.image.upload');
        Route::delete('/products/{product}/image', [ProductController::class, 'destroyImage'])->name('products.image.destroy');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/package-types', [PackageTypeController::class, 'index'])->name('package-types.index');
        Route::post('/package-types', [PackageTypeController::class, 'store'])->name('package-types.store');
        Route::patch('/package-types/{packageType}', [PackageTypeController::class, 'update'])->name('package-types.update');
        Route::delete('/package-types/{packageType}', [PackageTypeController::class, 'destroy'])->name('package-types.destroy');

        Route::get('/uoms', [UomController::class, 'index'])->name('uoms.index');
        Route::post('/uom-categories', [UomController::class, 'storeCategory'])->name('uom-categories.store');
        Route::delete('/uom-categories/{category}', [UomController::class, 'destroyCategory'])->name('uom-categories.destroy');
        Route::post('/uoms', [UomController::class, 'store'])->name('uoms.store');
        Route::patch('/uoms/{uom}', [UomController::class, 'update'])->name('uoms.update');
        Route::delete('/uoms/{uom}', [UomController::class, 'destroy'])->name('uoms.destroy');
        Route::post('/products/{product}/kit-components', [KitComponentController::class, 'store'])->name('kit-components.store');
        Route::delete('/kit-components/{component}', [KitComponentController::class, 'destroy'])->name('kit-components.destroy');

        Route::get('/lots', [LotController::class, 'index'])->name('lots.index');
        Route::post('/lots', [LotController::class, 'store'])->name('lots.store');
        Route::get('/lots/{lot}/trace', [LotController::class, 'trace'])->name('lots.trace');
        Route::patch('/lots/{lot}', [LotController::class, 'update'])->name('lots.update');
        Route::delete('/lots/{lot}', [LotController::class, 'destroy'])->name('lots.destroy');

        Route::get('/templates', [ProductTemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [ProductTemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}', [ProductTemplateController::class, 'show'])->name('templates.show');
        Route::delete('/templates/{template}', [ProductTemplateController::class, 'destroy'])->name('templates.destroy');
        Route::get('/attributes', [AttributeController::class, 'index'])->name('attributes.index');
        Route::post('/attributes', [AttributeController::class, 'store'])->name('attributes.store');
        Route::patch('/attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
        Route::post('/attributes/{attribute}/archive', [AttributeController::class, 'archive'])->name('attributes.archive');
        Route::post('/attributes/{attribute}/restore', [AttributeController::class, 'restore'])->name('attributes.restore');
        Route::delete('/attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');
        Route::post('/attributes/{attribute}/values', [AttributeController::class, 'storeValue'])->name('attributes.values.store');
        Route::post('/attributes/{attribute}/reorder/{direction}', [AttributeController::class, 'reorder'])->whereIn('direction', ['up', 'down'])->name('attributes.reorder');
        Route::patch('/attribute-values/{value}', [AttributeController::class, 'updateValue'])->name('attribute-values.update');
        Route::post('/attribute-values/{value}/image', [AttributeController::class, 'uploadValueImage'])->name('attribute-values.image.upload');
        Route::delete('/attribute-values/{value}/image', [AttributeController::class, 'destroyValueImage'])->name('attribute-values.image.destroy');
        Route::post('/attribute-values/{value}/reorder/{direction}', [AttributeController::class, 'reorderValue'])->whereIn('direction', ['up', 'down'])->name('attribute-values.reorder');
        Route::delete('/attribute-values/{value}', [AttributeController::class, 'destroyValue'])->name('attribute-values.destroy');
        Route::post('/templates/{template}/attributes', [ProductTemplateController::class, 'attachAttribute'])->name('templates.attributes.attach');
        Route::post('/templates/{template}/exclusions', [ProductTemplateController::class, 'storeExclusion'])->name('templates.exclusions.store');
        Route::delete('/exclusions/{exclusion}', [ProductTemplateController::class, 'destroyExclusion'])->name('exclusions.destroy');
    });

    Route::middleware('permission:manage warehouses,web')->group(function (): void {
        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::patch('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::post('/locations', [WarehouseController::class, 'storeLocation'])->name('locations.store');

        Route::get('/operation-types', [OperationTypeController::class, 'index'])->name('operation-types.index');
        Route::post('/operation-types', [OperationTypeController::class, 'store'])->name('operation-types.store');
        Route::patch('/operation-types/{operationType}', [OperationTypeController::class, 'update'])->name('operation-types.update');
        Route::delete('/operation-types/{operationType}', [OperationTypeController::class, 'destroy'])->name('operation-types.destroy');

        Route::get('/storage-categories', [StorageCategoryController::class, 'index'])->name('storage-categories.index');
        Route::post('/storage-categories', [StorageCategoryController::class, 'store'])->name('storage-categories.store');
        Route::patch('/storage-categories/{storageCategory}', [StorageCategoryController::class, 'update'])->name('storage-categories.update');
        Route::delete('/storage-categories/{storageCategory}', [StorageCategoryController::class, 'destroy'])->name('storage-categories.destroy');
    });

    Route::middleware('permission:perform stock counts,web')->group(function (): void {
        Route::get('/barcode', [BarcodeController::class, 'index'])->name('barcode.index');
        Route::get('/barcode/lookup', [BarcodeController::class, 'lookup'])->name('barcode.lookup');
        Route::post('/barcode/move', [BarcodeController::class, 'move'])->name('barcode.move');
    });

    Route::middleware('permission:view stock,web')->group(function (): void {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');

        Route::get('/reports/moves', [ReportController::class, 'moves'])->name('reports.moves');
        Route::get('/reports/valuation', [ReportController::class, 'valuation'])->name('reports.valuation');
        Route::get('/reports/locations', [ReportController::class, 'locations'])->name('reports.locations');
        Route::get('/reports/forecasted', [ReportController::class, 'forecasted'])->name('reports.forecasted');
        Route::get('/reports/warehouse-analysis', [ReportController::class, 'warehouseAnalysis'])->name('reports.warehouse-analysis');
        Route::get('/reports/consignment', [ReportController::class, 'consignment'])->name('reports.consignment');
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

    Route::middleware('permission:approve landed costs,web')->group(function (): void {
        Route::get('/landed-costs', [LandedCostController::class, 'index'])->name('landed-costs.index');
        Route::post('/landed-costs', [LandedCostController::class, 'store'])->name('landed-costs.store');
        Route::get('/landed-costs/{landedCost}', [LandedCostController::class, 'show'])->name('landed-costs.show');
        Route::post('/landed-costs/{landedCost}/lines', [LandedCostController::class, 'storeLine'])->name('landed-costs.lines.store');
        Route::delete('/landed-costs/lines/{line}', [LandedCostController::class, 'destroyLine'])->name('landed-costs.lines.destroy');
        Route::post('/landed-costs/{landedCost}/validate', [LandedCostController::class, 'validateLandedCost'])->name('landed-costs.validate');
    });

    Route::middleware('permission:manage warehouse transfers,web')->group(function (): void {
        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('/transfers/{transfer}/complete', [TransferController::class, 'complete'])->name('transfers.complete');

        Route::get('/transfer-batches', [TransferBatchController::class, 'index'])->name('transfer-batches.index');
        Route::post('/transfer-batches', [TransferBatchController::class, 'store'])->name('transfer-batches.store');
        Route::get('/transfer-batches/{batch}', [TransferBatchController::class, 'show'])->name('transfer-batches.show');
        Route::post('/transfer-batches/{batch}/transfers', [TransferBatchController::class, 'addTransfer'])->name('transfer-batches.transfers.add');
        Route::delete('/transfer-batches/transfers/{transfer}', [TransferBatchController::class, 'removeTransfer'])->name('transfer-batches.transfers.remove');
        Route::post('/transfer-batches/{batch}/complete', [TransferBatchController::class, 'complete'])->name('transfer-batches.complete');
        Route::post('/transfer-batches/{batch}/cancel', [TransferBatchController::class, 'cancel'])->name('transfer-batches.cancel');
    });

    Route::middleware('permission:perform scrap operations,web')->group(function (): void {
        Route::get('/scraps', [ScrapController::class, 'index'])->name('scraps.index');
        Route::post('/scraps', [ScrapController::class, 'store'])->name('scraps.store');
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
        Route::patch('/routes/rules/{rule}', [RouteController::class, 'updateRule'])->name('routes.rules.update');
        Route::delete('/routes/rules/{rule}', [RouteController::class, 'destroyRule'])->name('routes.rules.destroy');
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
