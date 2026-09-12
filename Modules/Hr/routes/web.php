<?php

use Illuminate\Support\Facades\Route;
use Modules\Hr\Http\Controllers\DepartmentController;
use Modules\Hr\Http\Controllers\EmployeeController;

Route::middleware(['auth:web', 'module:hr'])->prefix('app/hr')->name('app.hr.')->group(function (): void {
    Route::middleware('permission:manage employees,web')->group(function (): void {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });

    Route::middleware('permission:manage departments,web')->group(function (): void {
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    });
});
