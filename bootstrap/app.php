<?php

use App\Http\Middleware\EnsureModuleActive;
use App\Http\Middleware\SetPermissionsTeamId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        then: function (): void {
            Route::group([], base_path('routes/central.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            SetPermissionsTeamId::class,
        ]);

        $middleware->web(append: [
            SetPermissionsTeamId::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): ?string {
            return $request->expectsJson() ? null : route('login');
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            return auth('central_web')->check()
                ? route('central.web.tenants.index')
                : route('app.dashboard');
        });

        $middleware->alias([
            'module' => EnsureModuleActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
