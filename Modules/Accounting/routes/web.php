<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingSettingsController;
use Modules\Accounting\Http\Controllers\CardPaymentController;
use Modules\Accounting\Http\Controllers\CashBankAccountController;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\CheckAndNoteController;
use Modules\Accounting\Http\Controllers\CurrencyController;
use Modules\Accounting\Http\Controllers\ExchangeRateController;
use Modules\Accounting\Http\Controllers\InvoiceApprovalController;
use Modules\Accounting\Http\Controllers\JournalEntryViewerController;
use Modules\Accounting\Http\Controllers\PaymentController;
use Modules\Accounting\Http\Controllers\PosTerminalController;
use Modules\Accounting\Http\Controllers\PurchaseInvoiceController;
use Modules\Accounting\Http\Controllers\SalesInvoiceController;

Route::middleware(['auth:web', 'module:accounting'])->prefix('app/accounting')->name('app.accounting.')->group(function (): void {
    Route::middleware('permission:manage chart of accounts,web')->group(function (): void {
        Route::get('/accounts', [ChartOfAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [ChartOfAccountController::class, 'store'])->name('accounts.store');
        Route::patch('/accounts/{account}', [ChartOfAccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [ChartOfAccountController::class, 'destroy'])->name('accounts.destroy');

        Route::get('/journal-entries', [JournalEntryViewerController::class, 'index'])->name('journal-entries.index');
        Route::get('/journal-entries/{entry}', [JournalEntryViewerController::class, 'show'])->name('journal-entries.show');

        Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
        Route::post('/exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');
        Route::post('/exchange-rates/sync-tcmb', [ExchangeRateController::class, 'syncFromTcmb'])->name('exchange-rates.sync-tcmb');

        Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::get('/currencies/{currency}', [CurrencyController::class, 'show'])->name('currencies.show');
        Route::post('/currencies', [CurrencyController::class, 'store'])->name('currencies.store');
        Route::patch('/currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
        Route::post('/currencies/{currency}/archive', [CurrencyController::class, 'archive'])->name('currencies.archive');
        Route::post('/currencies/{currency}/restore', [CurrencyController::class, 'restore'])->name('currencies.restore');
        Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy'])->name('currencies.destroy');
    });

    Route::middleware('permission:post journal entries,web')->group(function (): void {
        Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index'])->name('purchase-invoices.index');
        Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store'])->name('purchase-invoices.store');
        Route::get('/purchase-invoices/{invoice}', [PurchaseInvoiceController::class, 'show'])->name('purchase-invoices.show');
        Route::post('/purchase-invoices/{invoice}/lines', [PurchaseInvoiceController::class, 'storeLine'])->name('purchase-invoices.lines.store');
        Route::post('/purchase-invoices/{invoice}/post', [PurchaseInvoiceController::class, 'post'])->name('purchase-invoices.post');

        Route::post('/purchase-invoices/{invoice}/e-invoice/send', [PurchaseInvoiceController::class, 'sendEInvoice'])->name('purchase-invoices.e-invoice.send');
        Route::post('/purchase-invoices/{invoice}/e-invoice/accept', [PurchaseInvoiceController::class, 'acceptEInvoice'])->name('purchase-invoices.e-invoice.accept');
        Route::post('/purchase-invoices/{invoice}/e-invoice/reject', [PurchaseInvoiceController::class, 'rejectEInvoice'])->name('purchase-invoices.e-invoice.reject');

        Route::get('/sales-invoices', [SalesInvoiceController::class, 'index'])->name('sales-invoices.index');
        Route::post('/sales-invoices', [SalesInvoiceController::class, 'store'])->name('sales-invoices.store');
        Route::get('/sales-invoices/{invoice}', [SalesInvoiceController::class, 'show'])->name('sales-invoices.show');
        Route::post('/sales-invoices/{invoice}/lines', [SalesInvoiceController::class, 'storeLine'])->name('sales-invoices.lines.store');
        Route::post('/sales-invoices/{invoice}/post', [SalesInvoiceController::class, 'post'])->name('sales-invoices.post');

        Route::post('/sales-invoices/{invoice}/e-invoice/send', [SalesInvoiceController::class, 'sendEInvoice'])->name('sales-invoices.e-invoice.send');
        Route::post('/sales-invoices/{invoice}/e-invoice/accept', [SalesInvoiceController::class, 'acceptEInvoice'])->name('sales-invoices.e-invoice.accept');
        Route::post('/sales-invoices/{invoice}/e-invoice/reject', [SalesInvoiceController::class, 'rejectEInvoice'])->name('sales-invoices.e-invoice.reject');
    });

    Route::middleware('permission:register payments,web')->group(function (): void {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{payment}/allocations', [PaymentController::class, 'storeAllocation'])->name('payments.allocations.store');

        Route::get('/receipts', [PaymentController::class, 'index'])->defaults('flow', 'receipt')->name('receipts.index');
        Route::get('/disbursements', [PaymentController::class, 'index'])->defaults('flow', 'payment')->name('disbursements.index');

        Route::get('/incoming-checks', [CheckAndNoteController::class, 'index'])->defaults('direction', 'incoming')->name('incoming-checks.index');
        Route::get('/outgoing-checks', [CheckAndNoteController::class, 'index'])->defaults('direction', 'outgoing')->name('outgoing-checks.index');
        Route::post('/checks-and-notes', [CheckAndNoteController::class, 'store'])->name('checks-and-notes.store');
        Route::get('/checks-and-notes/{note}', [CheckAndNoteController::class, 'show'])->name('checks-and-notes.show');
        Route::post('/checks-and-notes/{note}/endorse', [CheckAndNoteController::class, 'endorse'])->name('checks-and-notes.endorse');
        Route::post('/checks-and-notes/{note}/send-to-bank', [CheckAndNoteController::class, 'sendToBank'])->name('checks-and-notes.send-to-bank');
        Route::post('/checks-and-notes/{note}/collect', [CheckAndNoteController::class, 'markCollected'])->name('checks-and-notes.collect');
        Route::post('/checks-and-notes/{note}/bounce', [CheckAndNoteController::class, 'markBounced'])->name('checks-and-notes.bounce');
        Route::post('/checks-and-notes/{note}/pay', [CheckAndNoteController::class, 'markPaid'])->name('checks-and-notes.pay');

        Route::get('/card-payments', [CardPaymentController::class, 'index'])->name('card-payments.index');
        Route::post('/card-payments', [CardPaymentController::class, 'store'])->name('card-payments.store');
        Route::post('/card-payments/{card}/settle', [CardPaymentController::class, 'settle'])->name('card-payments.settle');
    });

    Route::middleware('role:Tenant Admin')->group(function (): void {
        Route::get('/pos-terminals', [PosTerminalController::class, 'index'])->name('pos-terminals.index');
        Route::post('/pos-terminals', [PosTerminalController::class, 'store'])->name('pos-terminals.store');
        Route::patch('/pos-terminals/{terminal}', [PosTerminalController::class, 'update'])->name('pos-terminals.update');
        Route::delete('/pos-terminals/{terminal}', [PosTerminalController::class, 'destroy'])->name('pos-terminals.destroy');
    });

    Route::middleware('permission:post journal entries,web')->group(function (): void {
        Route::post('/invoices/{invoice}/approval/submit', [InvoiceApprovalController::class, 'submit'])->name('invoices.approval.submit');
    });

    Route::middleware('role:Tenant Admin')->group(function (): void {
        Route::post('/invoices/{invoice}/approval/approve', [InvoiceApprovalController::class, 'approve'])->name('invoices.approval.approve');
        Route::post('/invoices/{invoice}/approval/reject', [InvoiceApprovalController::class, 'reject'])->name('invoices.approval.reject');

        Route::get('/settings', [AccountingSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [AccountingSettingsController::class, 'update'])->name('settings.update');

        Route::get('/cash-bank-accounts', [CashBankAccountController::class, 'index'])->name('cash-bank-accounts.index');
        Route::post('/cash-bank-accounts', [CashBankAccountController::class, 'store'])->name('cash-bank-accounts.store');
        Route::patch('/cash-bank-accounts/{journal}', [CashBankAccountController::class, 'update'])->name('cash-bank-accounts.update');
        Route::delete('/cash-bank-accounts/{journal}', [CashBankAccountController::class, 'destroy'])->name('cash-bank-accounts.destroy');
    });
});
