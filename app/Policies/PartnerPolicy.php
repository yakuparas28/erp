<?php

namespace App\Policies;

use App\Models\User;
use Modules\Inventory\Models\Partner;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage partners');
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->can('manage partners');
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->can('manage partners');
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->can('manage partners');
    }
}
