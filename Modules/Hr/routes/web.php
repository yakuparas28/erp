<?php

use Illuminate\Support\Facades\Route;
use Modules\Hr\Http\Controllers\ConsumptionRuleController;
use Modules\Hr\Http\Controllers\DepartmentController;
use Modules\Hr\Http\Controllers\EmployeeController;
use Modules\Hr\Http\Controllers\LeaveApprovalController;
use Modules\Hr\Http\Controllers\LeaveBalanceController;
use Modules\Hr\Http\Controllers\LeaveConfigController;
use Modules\Hr\Http\Controllers\LeaveRequestController;
use Modules\Hr\Http\Controllers\LeaveTypeController;
use Modules\Hr\Http\Controllers\PayrollController;

Route::middleware(['auth:web', 'module:hr'])->prefix('app/hr')->name('app.hr.')->group(function (): void {
    Route::middleware('permission:manage employees,web')->group(function (): void {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
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

    // Personel: 'submit own leave' izniyle sınırlı; Tenant Admin bu izne
    // PermissionCatalog::all() üzerinden zaten sahip.
    Route::middleware('permission:submit own leave,web')->group(function (): void {
        Route::get('/leaves/mine', [LeaveRequestController::class, 'index'])->name('leaves.mine');
        Route::post('/leaves', [LeaveRequestController::class, 'store'])->name('leaves.store');
        Route::post('/leaves/{leave}/cancel', [LeaveRequestController::class, 'cancel'])->name('leaves.cancel');
    });

    Route::middleware('permission:approve leave first level,web')->group(function (): void {
        Route::get('/leave-approvals/first-level', [LeaveApprovalController::class, 'firstLevelIndex'])->name('leave-approvals.first');
    });
    Route::middleware('permission:approve leave second level,web')->group(function (): void {
        Route::get('/leave-approvals/second-level', [LeaveApprovalController::class, 'secondLevelIndex'])->name('leave-approvals.second');
    });
    Route::post('/leave-approvals/{leave}/approve', [LeaveApprovalController::class, 'approve'])->name('leave-approvals.approve');
    Route::post('/leave-approvals/{leave}/reject', [LeaveApprovalController::class, 'reject'])->name('leave-approvals.reject');

    Route::middleware('permission:view leave monitoring,web')->group(function (): void {
        Route::get('/leave-monitoring', [LeaveApprovalController::class, 'monitoring'])->name('leave-monitoring.index');
    });

    Route::middleware('permission:manage leave balances,web')->group(function (): void {
        Route::get('/leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
        Route::post('/leave-balances', [LeaveBalanceController::class, 'upsert'])->name('leave-balances.upsert');
    });

    Route::middleware('permission:manage leave configuration,web')->group(function (): void {
        Route::get('/leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
        Route::post('/leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
        Route::patch('/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
        Route::delete('/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');

        Route::get('/leave-config', [LeaveConfigController::class, 'index'])->name('leave-config.index');
        Route::post('/leave-config/holidays', [LeaveConfigController::class, 'storeHoliday'])->name('leave-config.holidays.store');
        Route::delete('/leave-config/holidays/{holiday}', [LeaveConfigController::class, 'destroyHoliday'])->name('leave-config.holidays.destroy');
        Route::post('/leave-config/critical-dates', [LeaveConfigController::class, 'storeCriticalDate'])->name('leave-config.critical-dates.store');
        Route::delete('/leave-config/critical-dates/{criticalDate}', [LeaveConfigController::class, 'destroyCriticalDate'])->name('leave-config.critical-dates.destroy');
        Route::post('/leave-config/hour-configs', [LeaveConfigController::class, 'storeHourConfig'])->name('leave-config.hour-configs.store');
        Route::delete('/leave-config/hour-configs/{leaveHourConfig}', [LeaveConfigController::class, 'destroyHourConfig'])->name('leave-config.hour-configs.destroy');

        Route::get('/leave-config/hourly', [LeaveConfigController::class, 'hourlyIndex'])->name('leave-config.hourly.index');
        Route::patch('/leave-config/hourly/platform', [LeaveConfigController::class, 'updatePlatformHourly'])->name('leave-config.hourly.platform');
        Route::post('/leave-config/hourly/override', [LeaveConfigController::class, 'storeHourOverride'])->name('leave-config.hourly.override.store');
        Route::delete('/leave-config/hourly/override/{leaveHourConfig}', [LeaveConfigController::class, 'destroyHourOverride'])->name('leave-config.hourly.override.destroy');

        Route::get('/consumption-rules', [ConsumptionRuleController::class, 'index'])->name('consumption-rules.index');
        Route::post('/consumption-rules', [ConsumptionRuleController::class, 'store'])->name('consumption-rules.store');
        Route::patch('/consumption-rules/{consumptionRule}', [ConsumptionRuleController::class, 'update'])->name('consumption-rules.update');
        Route::delete('/consumption-rules/{consumptionRule}', [ConsumptionRuleController::class, 'destroy'])->name('consumption-rules.destroy');
    });

    // Bordro: finansal etkisi olduğu için sadece Tenant Admin (Seviye 1 basit
    // bordro; Seviye 2/3 tanıtılırsa "manage payroll" permission'ı ayrılabilir).
    Route::middleware('role:Tenant Admin')->group(function (): void {
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('/payroll/{period}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('/payroll/{period}/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('/payroll/{period}/post', [PayrollController::class, 'post'])->name('payroll.post');
        Route::post('/payroll/payslips/{slip}/pay', [PayrollController::class, 'pay'])->name('payroll.payslips.pay');
    });
});
