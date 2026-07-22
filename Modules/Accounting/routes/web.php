<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\JournalEntryViewerController;

Route::middleware(['auth:web'])->prefix('app/accounting')->name('app.accounting.')->group(function (): void {
    Route::middleware('permission:manage chart of accounts,web')->group(function (): void {
        Route::get('/accounts', [ChartOfAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [ChartOfAccountController::class, 'store'])->name('accounts.store');
        Route::delete('/accounts/{account}', [ChartOfAccountController::class, 'destroy'])->name('accounts.destroy');

        Route::get('/journal-entries', [JournalEntryViewerController::class, 'index'])->name('journal-entries.index');
        Route::get('/journal-entries/{entry}', [JournalEntryViewerController::class, 'show'])->name('journal-entries.show');
    });
});
