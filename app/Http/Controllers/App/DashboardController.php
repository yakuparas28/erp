<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\TenantModuleActivation;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tenant = $user->tenant;

        $activations = TenantModuleActivation::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('module_id');

        return view('app.dashboard', [
            'tenant' => $tenant,
            'subscription' => TenantSubscription::with('licensePackage')->where('tenant_id', $tenant->id)->first(),
            'modules' => Module::orderByDesc('is_core')->orderBy('name')->get()
                ->map(fn (Module $module) => [
                    'module' => $module,
                    'isActive' => $module->is_core || $activations->contains($module->id),
                ]),
        ]);
    }
}
