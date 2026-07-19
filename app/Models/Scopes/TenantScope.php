<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant izolasyonu: kimliği doğrulanmış kullanıcının tenant_id'si doluysa
 * tüm sorgulara WHERE tenant_id = X ekler. SuperAdmin modelinde tenant_id
 * kolonu bulunmadığından bu scope super_admin guard'ında hiç devreye girmez.
 * Queue/console bağlamında auth olmadığı için scope uygulanmaz — tenant_id
 * job'lara daima explicit parametre olarak taşınmalıdır (PRD 4.2).
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = auth()->hasUser() ? auth()->user()->getAttribute('tenant_id') : null;

        if ($tenantId !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }
}
