<?php

namespace App\Policies;

use App\Models\User;
use Modules\Expenses\Models\Expense;
use Modules\Hr\Models\Employee;

/**
 * Masraf yetkilendirme kuralları — sahiplik + statü + rol kombinasyonu
 * tek yerde. Controller'lardan `abort_unless(...)` blokları buraya
 * çekildi; test edilebilirlik + kural tekilliği.
 *
 * Not: BelongsToTenant global scope tenant izolasyonunu zaten sağlıyor;
 * policy sadece tenant içi ownership/status kontrolü yapıyor.
 */
class ExpensePolicy
{
    /** Kendi masrafını gören her personel + tüm onaylayıcılar. */
    public function view(User $user, Expense $expense): bool
    {
        if ($user->can('approve expense')) {
            return true;
        }

        return $this->isOwner($user, $expense);
    }

    /** Sadece taslak/reddedilen masrafı sahibi düzenler. */
    public function update(User $user, Expense $expense): bool
    {
        return $this->isOwner($user, $expense) && $expense->isEditable();
    }

    /** Sadece taslak masrafı sahibi siler. */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->isOwner($user, $expense) && $expense->status === Expense::STATUS_DRAFT;
    }

    /** Sahibi kendi taslağını/reddini gönderir. */
    public function submit(User $user, Expense $expense): bool
    {
        return $this->isOwner($user, $expense) && $expense->isEditable();
    }

    /** Approve/refuse: `approve expense` izni + submitted statü. */
    public function approve(User $user, Expense $expense): bool
    {
        return $user->can('approve expense') && $expense->status === Expense::STATUS_SUBMITTED;
    }

    public function refuse(User $user, Expense $expense): bool
    {
        return $this->approve($user, $expense);
    }

    /** Muhasebeye postala: `post expense` izni + approved statü. */
    public function post(User $user, Expense $expense): bool
    {
        return $user->can('post expense') && $expense->status === Expense::STATUS_APPROVED;
    }

    private function isOwner(User $user, Expense $expense): bool
    {
        $employee = Employee::where('user_id', $user->id)->first();

        return $employee !== null && $expense->employee_id === $employee->id;
    }
}
