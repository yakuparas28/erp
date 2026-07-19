<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingController;

Route::middleware(['auth:sanctum', 'module:accounting'])->prefix('v1')->group(function () {
    Route::apiResource('accountings', AccountingController::class)->names('accounting');
});
