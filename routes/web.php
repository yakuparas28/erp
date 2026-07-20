<?php

use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\MailSettingController;
use App\Http\Controllers\App\NotificationTemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Central\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'))->name('home');

Route::middleware('guest:central_web,web')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
Route::post('/impersonation/leave', [ImpersonationController::class, 'destroy'])->name('impersonation.leave');

Route::middleware('auth:web')->prefix('app')->name('app.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:Tenant Admin,web')->group(function (): void {
        Route::get('/settings/mail', [MailSettingController::class, 'edit'])->name('settings.mail');
        Route::put('/settings/mail', [MailSettingController::class, 'update'])->name('settings.mail.update');

        Route::get('/settings/notification-templates', [NotificationTemplateController::class, 'index'])->name('settings.templates');
        Route::put('/settings/notification-templates/{key}', [NotificationTemplateController::class, 'update'])->name('settings.templates.update');
        Route::delete('/settings/notification-templates/{key}', [NotificationTemplateController::class, 'destroy'])->name('settings.templates.reset');
    });
});
