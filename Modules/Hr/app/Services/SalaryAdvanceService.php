<?php

namespace Modules\Hr\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\SalaryAdvance;

/**
 * Personel avansı: verildiğinde nakit çıkar (dr 196 / cr banka/kasa),
 * mahsup edildiğinde payslip ödemesinden düşülür (dr 335 / cr 196).
 * Mahsup PayrollService::paySlip()'te yapılır — bu servis sadece
 * avansın verilme/iptal ve outstanding sorgusu ile ilgilenir.
 */
class SalaryAdvanceService
{
    public function __construct(
        private readonly JournalEntryService $journalEntries,
        private readonly AccountingDefaultsService $defaults,
    ) {}

    public function grant(Employee $employee, string $amount, int $journalId, User $creator, ?string $notes = null): SalaryAdvance
    {
        abort_if(bccomp($amount, '0', 4) <= 0, 422, __('Advance amount must be greater than zero.'));

        $journal = Journal::withoutGlobalScopes()->findOrFail($journalId);
        abort_unless(
            in_array($journal->type, ['cash', 'bank'], true) && $journal->tenant_id === $employee->tenant_id,
            422,
            __('Invalid journal.'),
        );

        return DB::transaction(function () use ($employee, $amount, $journal, $creator, $notes) {
            $advance = new SalaryAdvance([
                'employee_id' => $employee->id,
                'amount' => $amount,
                'granted_at' => now()->toDateString(),
                'paid_from_journal_id' => $journal->id,
                'status' => SalaryAdvance::STATUS_OUTSTANDING,
                'notes' => $notes,
                'created_by' => $creator->id,
            ]);
            $advance->tenant_id = $employee->tenant_id;
            $advance->save();

            $advanceAccount = $this->defaults->accountByCode($employee->tenant_id, '196');
            $cashAccount = $journal->chart_of_account_id !== null
                ? ChartOfAccount::withoutGlobalScopes()->findOrFail($journal->chart_of_account_id)
                : $this->defaults->accountByCode($employee->tenant_id, $journal->type === 'cash' ? '100' : '102');

            $this->journalEntries->write(
                tenantId: $employee->tenant_id,
                journalType: $journal->type,
                entryDate: now()->toDateString(),
                reference: $advance,
                lines: [
                    ['account_id' => $advanceAccount->id, 'debit' => $amount, 'credit' => '0.0000'],
                    ['account_id' => $cashAccount->id, 'debit' => '0.0000', 'credit' => $amount],
                ],
            );

            return $advance->fresh();
        });
    }

    /**
     * Bir personelin outstanding avans toplamı.
     */
    public function outstandingTotal(int $employeeId): string
    {
        $sum = SalaryAdvance::withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('status', SalaryAdvance::STATUS_OUTSTANDING)
            ->sum('amount');

        return bcadd((string) $sum, '0', 4);
    }

    /**
     * Bir personelin outstanding avans kayıtları (mahsupta FIFO
     * uygulanacak — en eskiden başla).
     *
     * @return Collection<int, SalaryAdvance>
     */
    public function outstandingList(int $employeeId): Collection
    {
        return SalaryAdvance::withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('status', SalaryAdvance::STATUS_OUTSTANDING)
            ->orderBy('granted_at')->orderBy('id')
            ->get();
    }

    /**
     * Payslip ödemesi sırasında çağrılır: mahsup edilen avansları
     * `deducted` olarak işaretler, hangi payslip'te düşüldüğünü kaydeder.
     * Kısmi mahsup varsa (avans > net) fazla avans outstanding kalır —
     * bu senaryo bordroda net = 0 üretecek şekilde `applyToPayslip` ile
     * cap'lendiği için burada tam mahsup varsayılır.
     *
     * @param  array<int, int>  $advanceIds
     */
    public function markDeducted(array $advanceIds, int $payslipId): void
    {
        if ($advanceIds === []) {
            return;
        }
        SalaryAdvance::withoutGlobalScopes()
            ->whereIn('id', $advanceIds)
            ->update([
                'status' => SalaryAdvance::STATUS_DEDUCTED,
                'deducted_in_payslip_id' => $payslipId,
                'deducted_at' => now()->toDateString(),
            ]);
    }
}
