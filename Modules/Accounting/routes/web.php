<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\ExchangeRateController;
use Modules\Accounting\Http\Controllers\JournalEntryViewerController;
use Modules\Accounting\Http\Controllers\PaymentController;
use Modules\Accounting\Http\Controllers\PurchaseInvoiceController;
use Modules\Accounting\Http\Controllers\SalesInvoiceController;

Route::middleware(['auth:web'])->prefix('app/accounting')->name('app.accounting.')->group(function (): void {
    Route::middleware('permission:manage chart of accounts,web')->group(function (): void {
        Route::get('/accounts', [ChartOfAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [ChartOfAccountController::class, 'store'])->name('accounts.store');
        Route::delete('/accounts/{account}', [ChartOfAccountController::class, 'destroy'])->name('accounts.destroy');

        Route::get('/journal-entries', [JournalEntryViewerController::class, 'index'])->name('journal-entries.index');
        Route::get('/journal-entries/{entry}', [JournalEntryViewerController::class, 'show'])->name('journal-entries.show');

        Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
        Route::post('/exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');
    });

    Route::middleware('permission:post journal entries,web')->group(function (): void {
        Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index'])->name('purchase-invoices.index');
        Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store'])->name('purchase-invoices.store');
        Route::get('/purchase-invoices/{invoice}', [PurchaseInvoiceController::class, 'show'])->name('purchase-invoices.show');
        Route::post('/purchase-invoices/{invoice}/lines', [PurchaseInvoiceController::class, 'storeLine'])->name('purchase-invoices.lines.store');
        Route::post('/purchase-invoices/{invoice}/post', [PurchaseInvoiceController::class, 'post'])->name('purchase-invoices.post');

        Route::get('/sales-invoices', [SalesInvoiceController::class, 'index'])->name('sales-invoices.index');
        Route::post('/sales-invoices', [SalesInvoiceController::class, 'store'])->name('sales-invoices.store');
        Route::get('/sales-invoices/{invoice}', [SalesInvoiceController::class, 'show'])->name('sales-invoices.show');
        Route::post('/sales-invoices/{invoice}/lines', [SalesInvoiceController::class, 'storeLine'])->name('sales-invoices.lines.store');
        Route::post('/sales-invoices/{invoice}/post', [SalesInvoiceController::class, 'post'])->name('sales-invoices.post');
    });

    Route::middleware('permission:register payments,web')->group(function (): void {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/allocations', [PaymentController::class, 'storeAllocation'])->name('payments.allocations.store');
    });
});
