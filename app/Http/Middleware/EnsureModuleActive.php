<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Models\TenantModuleActivation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route'a atanmış modül anahtarını tenant'ın runtime aktivasyonlarıyla
 * karşılaştırır (PRD 3.16). Modül erişim kontrolü YALNIZCA bu merkezi
 * katmanda yapılır; controller'lar kendi içinde ayrıca kontrol etmez
 * (PRD 4.2 'Modül Erişim Zorunluluğu'). Çekirdek modüller daima geçer.
 */
class EnsureModuleActive
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $module = Module::where('key', $moduleKey)->first();

        abort_if($module === null, 500, "Tanımsız modül anahtarı: {$moduleKey}");

        if ($module->is_core) {
            return $next($request);
        }

        $tenantId = $request->user()?->getAttribute('tenant_id');

        $isActive = $tenantId !== null && TenantModuleActivation::where('tenant_id', $tenantId)
            ->where('module_id', $module->id)
            ->where('is_active', true)
            ->exists();

        abort_unless($isActive, 403, 'Bu modül paketinizde aktif değil');

        return $next($request);
    }
}
