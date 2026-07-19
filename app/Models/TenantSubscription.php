<?php

namespace App\Models;

use Database\Factories\TenantSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform yönetimli tablo — BelongsToTenant BİLEREK kullanılmaz;
 * sorgular explicit tenant_id ile yapılır (Süper Admin bağlamı).
 */
#[Fillable(['tenant_id', 'license_package_id', 'status', 'starts_at', 'ends_at'])]
class TenantSubscription extends Model
{
    /** @use HasFactory<TenantSubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function licensePackage(): BelongsTo
    {
        return $this->belongsTo(LicensePackage::class);
    }
}
