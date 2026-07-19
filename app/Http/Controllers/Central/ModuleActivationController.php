<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Tenant;
use App\Services\Platform\ModuleActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ModuleActivationController extends Controller
{
    public function __construct(private readonly ModuleActivationService $activations) {}

    public function store(Request $request, Tenant $tenant, Module $module): JsonResponse
    {
        $activation = $this->activations->activateManually(
            $tenant,
            $module,
            $request->user('super_admin'),
        );

        activity()
            ->causedBy($request->user('super_admin'))
            ->performedOn($activation)
            ->withProperties(['module' => $module->key, 'source' => 'manual_addon'])
            ->log('module.activated');

        return response()->json($activation, 201);
    }

    public function destroy(Request $request, Tenant $tenant, Module $module): JsonResponse
    {
        try {
            $this->activations->deactivate($tenant, $module, $request->user('super_admin'));
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        activity()
            ->causedBy($request->user('super_admin'))
            ->withProperties(['module' => $module->key, 'tenant_id' => $tenant->id])
            ->log('module.deactivated');

        return response()->json(status: 204);
    }
}
