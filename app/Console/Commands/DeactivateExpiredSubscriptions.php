<?php

namespace App\Console\Commands;

use App\Models\TenantSubscription;
use Illuminate\Console\Command;

class DeactivateExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:deactivate-expired';

    protected $description = 'Süresi dolan abonelikleri iptal eder ve paket modüllerini pasifleştirir';

    public function handle(): int
    {
        $expired = TenantSubscription::whereIn('status', ['trial', 'active'])
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->get();

        foreach ($expired as $subscription) {
            // Observer, status değişimini yakalayıp paket aktivasyonlarını düşürür.
            $subscription->update(['status' => 'cancelled']);

            activity()
                ->performedOn($subscription)
                ->withProperties(['tenant_id' => $subscription->tenant_id, 'ends_at' => $subscription->ends_at->toDateString()])
                ->log('subscription.expired');

            $this->info("Tenant {$subscription->tenant_id}: abonelik süresi doldu, modüller pasifleştirildi.");
        }

        $this->info("Toplam {$expired->count()} abonelik işlendi.");

        return self::SUCCESS;
    }
}
