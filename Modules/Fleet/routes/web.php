<?php

use Illuminate\Support\Facades\Route;
use Modules\Fleet\Http\Controllers\Admin\ApprovalController;
use Modules\Fleet\Http\Controllers\Admin\DeliveryController;
use Modules\Fleet\Http\Controllers\Admin\FleetDashboardController;
use Modules\Fleet\Http\Controllers\Admin\FleetTaskSettingsController;
use Modules\Fleet\Http\Controllers\Admin\MaintenanceRecordController;
use Modules\Fleet\Http\Controllers\Admin\ProjectController;
use Modules\Fleet\Http\Controllers\Admin\VehicleCalendarBlockController;
use Modules\Fleet\Http\Controllers\Admin\VehicleController;
use Modules\Fleet\Http\Controllers\Admin\VehicleUsageReportController;
use Modules\Fleet\Http\Controllers\Admin\VehicleUsageRuleController;
use Modules\Fleet\Http\Controllers\FleetCalendarController;
use Modules\Fleet\Http\Controllers\ReservationController;

Route::middleware(['auth:web', 'module:fleet'])->prefix('app/fleet')->name('app.fleet.')->group(function (): void {

    // Personel: taleplerim + iki-adım rezervasyon oluşturma
    Route::middleware('permission:view own reservations,web')->group(function (): void {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    });
    Route::middleware('permission:reserve vehicle,web')->group(function (): void {
        Route::get('/reservations/new', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    });
    Route::middleware('permission:confirm own pickup,web')->group(function (): void {
        Route::get('/reservations/{reservation}/pickup', [ReservationController::class, 'showPickup'])->name('reservations.pickup');
        Route::post('/reservations/{reservation}/pickup', [ReservationController::class, 'confirmPickup'])->name('reservations.pickup.store');
    });
    Route::middleware('permission:submit own delivery,web')->group(function (): void {
        Route::get('/reservations/{reservation}/delivery', [ReservationController::class, 'showDelivery'])->name('reservations.delivery');
        Route::post('/reservations/{reservation}/delivery', [ReservationController::class, 'submitDelivery'])->name('reservations.delivery.store');
    });

    // Filo takvimi (personel + FY tarafından görülebilir)
    Route::middleware('permission:view fleet calendar,web')->group(function (): void {
        Route::get('/calendar', [FleetCalendarController::class, 'index'])->name('calendar.index');
    });
    Route::middleware('permission:reserve vehicle,web')->group(function (): void {
        Route::post('/calendar/reserve', [FleetCalendarController::class, 'reserve'])->name('calendar.reserve');
    });

    // Admin: Filo Yöneticisi
    Route::middleware('permission:view fleet dashboard,web')->group(function (): void {
        Route::get('/dashboard', [FleetDashboardController::class, 'index'])->name('dashboard');
    });

    Route::middleware('permission:manage vehicles,web')->group(function (): void {
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');

        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    });

    Route::middleware('can:approve-vehicle-request')->group(function (): void {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{reservation}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{reservation}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
    });

    Route::middleware('permission:confirm vehicle delivery,web')->group(function (): void {
        Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::post('/deliveries/{reservation}/confirm', [DeliveryController::class, 'confirm'])->name('deliveries.confirm');
        Route::post('/deliveries/{reservation}/force-garage', [DeliveryController::class, 'forceGarage'])->name('deliveries.force-garage');
    });

    Route::middleware('permission:manage vehicle calendar,web')->group(function (): void {
        Route::get('/vehicle-calendar', [VehicleCalendarBlockController::class, 'index'])->name('vehicle-calendar.index');
        Route::get('/vehicle-calendar/{vehicle}', [VehicleCalendarBlockController::class, 'show'])->name('vehicle-calendar.show');
        Route::post('/vehicle-calendar/{vehicle}/blocks', [VehicleCalendarBlockController::class, 'store'])->name('vehicle-calendar.blocks.store');
        Route::delete('/vehicle-calendar/blocks/{block}', [VehicleCalendarBlockController::class, 'destroy'])->name('vehicle-calendar.blocks.destroy');
    });

    Route::middleware('permission:manage usage rules,web')->group(function (): void {
        Route::get('/usage-rules', [VehicleUsageRuleController::class, 'show'])->name('usage-rules.show');
        Route::put('/usage-rules', [VehicleUsageRuleController::class, 'update'])->name('usage-rules.update');
    });

    Route::middleware('permission:view usage report,web')->group(function (): void {
        Route::get('/reports/usage', [VehicleUsageReportController::class, 'index'])->name('reports.usage');
    });

    Route::middleware('permission:manage maintenance records,web')->group(function (): void {
        Route::get('/maintenance', [MaintenanceRecordController::class, 'index'])->name('maintenance.index');
        Route::post('/vehicles/{vehicle}/maintenance', [MaintenanceRecordController::class, 'store'])->name('maintenance.store');
    });

    Route::middleware('permission:manage fleet task settings,web')->group(function (): void {
        Route::get('/settings/tasks', [FleetTaskSettingsController::class, 'show'])->name('settings.tasks');
        Route::put('/settings/tasks', [FleetTaskSettingsController::class, 'update'])->name('settings.tasks.update');
    });
    Route::middleware('permission:run critical window,web')->group(function (): void {
        Route::post('/settings/tasks/run-critical-window', [FleetTaskSettingsController::class, 'runCriticalWindow'])->name('settings.tasks.run-critical-window');
    });
});
