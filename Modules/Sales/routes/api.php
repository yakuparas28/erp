<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesController;

Route::middleware(['auth:sanctum', 'module:sales'])->prefix('v1')->group(function () {
    Route::apiResource('sales', SalesController::class)->names('sales');
});
