<?php

namespace App\Policies;

use App\Models\User;
use Modules\Purchase\Models\PurchaseOrder;

/**
 * Satın alma siparişi yetkileri. Kural: aynı kullanıcı kendi oluşturduğu
 * PO'yu onaylayamaz (4-göz prensibi) — bu daha önce controller'da
 * `$canConfirm` değişkeni olarak dağıtılmıştı, burada tek yerde.
 */
class PurchaseOrderPolicy
{
    public function view(User $user, PurchaseOrder $po): bool
    {
        return $user->can('create purchase orders') || $user->can('confirm purchase orders');
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $po->status === 'draft' && $user->can('create purchase orders');
    }

    public function sendRfq(User $user, PurchaseOrder $po): bool
    {
        return $po->status === 'draft' && $user->can('create purchase orders');
    }

    /** 4-göz: oluşturan onaylayamaz. */
    public function confirm(User $user, PurchaseOrder $po): bool
    {
        return $po->status === 'rfq_sent'
            && $po->created_by !== $user->id
            && $user->can('confirm purchase orders');
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        return ! in_array($po->status, ['cancelled', 'done'], true)
            && ($user->can('create purchase orders') || $user->can('confirm purchase orders'));
    }
}
