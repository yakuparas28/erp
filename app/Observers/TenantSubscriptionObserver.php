<?php

namespace App\Observers;

use App\Models\TenantSubscription;
use App\Services\Platform\ModuleActivationService;

class TenantSubscriptionObserver
{
    public function __construct(private readonly ModuleActivationService $activations) {}

    public function created(TenantSubscription $subscription): void
    {
        $this->activations->syncFromPackage($subscription);
    }

    public function updated(TenantSubscription $subscription): void
    {
        if ($subscription->wasChanged(['license_package_id', 'status', 'ends_at'])) {
            $this->activations->syncFromPackage($subscription);
        }
    }
}
