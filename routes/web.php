<?php

use App\Http\Controllers\App\ApprovalWorkflowController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\MailSettingController;
use App\Http\Controllers\App\NotificationTemplateController;
use App\Http\Controllers\App\RoleController;
use App\Http\Controllers\App\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Central\ImpersonationController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\PortalQuoteController;

Route::get('/', fn () => redirect()->route('login'))->name('home');

Route::middleware('guest:central_web,web')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');
Route::post('/impersonation/leave', [ImpersonationController::class, 'destroy'])->name('impersonation.leave');

Route::get('/q/{token}', [PortalQuoteController::class, 'show'])->name('portal.quote');
Route::post('/q/{token}/accept', [PortalQuoteController::class, 'accept'])->name('portal.quote.accept');
Route::post('/q/{token}/decline', [PortalQuoteController::class, 'decline'])->name('portal.quote.decline');

Route::middleware('auth:web')->prefix('app')->name('app.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('permission:manage users,web')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware('permission:manage roles,web')->group(function (): void {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    Route::middleware('permission:manage settings,web')->group(function (): void {
        Route::get('/settings/mail', [MailSettingController::class, 'edit'])->name('settings.mail');
        Route::put('/settings/mail', [MailSettingController::class, 'update'])->name('settings.mail.update');

        Route::get('/settings/notification-templates', [NotificationTemplateController::class, 'index'])->name('settings.templates');
        Route::put('/settings/notification-templates/{key}', [NotificationTemplateController::class, 'update'])->name('settings.templates.update');
        Route::delete('/settings/notification-templates/{key}', [NotificationTemplateController::class, 'destroy'])->name('settings.templates.reset');

        Route::get('/approval-workflows', [ApprovalWorkflowController::class, 'index'])->name('approval-workflows.index');
        Route::post('/approval-workflows', [ApprovalWorkflowController::class, 'store'])->name('approval-workflows.store');
        Route::get('/approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'show'])->name('approval-workflows.show');
        Route::patch('/approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'update'])->name('approval-workflows.update');
        Route::delete('/approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'destroy'])->name('approval-workflows.destroy');
        Route::post('/approval-workflows/{workflow}/steps', [ApprovalWorkflowController::class, 'storeStep'])->name('approval-workflows.steps.store');
        Route::delete('/approval-workflows/{workflow}/steps/{step}', [ApprovalWorkflowController::class, 'destroyStep'])->name('approval-workflows.steps.destroy');
    });
});
