<?php

namespace App\Policies;

use App\Models\User;
use Modules\Sales\Models\SalesOrder;

class SalesOrderPolicy
{
    public function view(User $user, SalesOrder $so): bool
    {
        return $user->can('create sales orders') || $user->can('confirm sales orders');
    }

    public function update(User $user, SalesOrder $so): bool
    {
        return $so->status === 'draft' && $user->can('create sales orders');
    }

    public function sendQuotation(User $user, SalesOrder $so): bool
    {
        return $so->status === 'draft' && $user->can('create sales orders');
    }

    /** 4-göz: oluşturan onaylayamaz. */
    public function confirm(User $user, SalesOrder $so): bool
    {
        return $so->status === 'quotation_sent'
            && $so->created_by !== $user->id
            && $user->can('confirm sales orders');
    }

    public function cancel(User $user, SalesOrder $so): bool
    {
        return ! in_array($so->status, ['cancelled', 'done'], true)
            && ($user->can('create sales orders') || $user->can('confirm sales orders'));
    }
}
