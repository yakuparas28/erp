<?php

use Illuminate\Support\Facades\Route;
use Modules\Expenses\Http\Controllers\ExpenseApprovalController;
use Modules\Expenses\Http\Controllers\ExpenseCategoryController;
use Modules\Expenses\Http\Controllers\ExpenseController;

Route::middleware(['auth:web', 'module:expenses'])->prefix('app/expenses')->name('app.expenses.')->group(function (): void {

    Route::middleware('permission:manage expense categories,web')->group(function (): void {
        Route::get('/categories', [ExpenseCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [ExpenseCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [ExpenseCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [ExpenseCategoryController::class, 'destroy'])->name('categories.destroy');
    });

    Route::middleware('permission:submit own expense,web')->group(function (): void {
        Route::get('/mine', [ExpenseController::class, 'index'])->name('mine');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
        Route::post('/{expense}/submit', [ExpenseController::class, 'submit'])->name('submit');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:approve expense,web')->group(function (): void {
        Route::get('/approvals', [ExpenseApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{expense}/approve', [ExpenseApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{expense}/refuse', [ExpenseApprovalController::class, 'refuse'])->name('approvals.refuse');
        Route::post('/approvals/{expense}/post', [ExpenseApprovalController::class, 'post'])->name('approvals.post');
    });
});
