<?php

namespace App\Models;

use Database\Factories\TenantModuleActivationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Runtime modül erişim kaynağı (PRD 3.16). Platform yönetimli tablo —
 * BelongsToTenant BİLEREK kullanılmaz; sorgular explicit tenant_id iledir.
 */
#[Fillable([
    'tenant_id',
    'module_id',
    'is_active',
    'source',
    'activated_by_super_admin_id',
    'activated_at',
    'deactivated_at',
])]
class TenantModuleActivation extends Model
{
    /** @use HasFactory<TenantModuleActivationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
