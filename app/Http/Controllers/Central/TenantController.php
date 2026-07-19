<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Tenant::latest()->get()]);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = Tenant::create($request->validated());

        activity()
            ->causedBy($request->user('super_admin'))
            ->performedOn($tenant)
            ->log('tenant.created');

        return response()->json($tenant, 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json($tenant->load('users'));
    }
}
