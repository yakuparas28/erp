<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreSubscriptionRequest;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    /**
     * Tenant'a abonelik atar veya mevcut aboneliğin paketini değiştirir;
     * aktivasyon senkronu TenantSubscriptionObserver üzerinden tetiklenir.
     */
    public function store(StoreSubscriptionRequest $request, Tenant $tenant): JsonResponse
    {
        $subscription = TenantSubscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $request->validated(),
        );

        activity()
            ->causedBy($request->user('super_admin'))
            ->performedOn($subscription)
            ->withProperties(['license_package_id' => $subscription->license_package_id])
            ->log('subscription.assigned');

        return response()->json($subscription, 201);
    }
}
