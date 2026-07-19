<?php

use App\Http\Controllers\Central\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/central')->name('central.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:super_admin')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
    });
});
