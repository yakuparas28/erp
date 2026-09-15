<?php

namespace App\Policies;

use App\Models\User;
use Modules\Hr\Models\Employee;

/**
 * Personel: tenant admin/HR yönetici hepsini yönetir; personel kendi
 * profilini görebilir (self-view). Manager (direkt yönetici) direkt
 * ekibini görebilir — bu leaf-view özelliği ileride ekliyoruz; şimdilik
 * yalnızca manage employees izniyle yetki verilir.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage employees');
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->can('manage employees')) {
            return true;
        }

        return $employee->user_id === $user->id;
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('manage employees');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('manage employees') && $employee->user_id !== $user->id;
    }
}
