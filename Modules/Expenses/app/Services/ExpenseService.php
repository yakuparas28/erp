<?php

namespace Modules\Expenses\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;
use Modules\Expenses\Models\Expense;
use Modules\Expenses\Models\ExpenseCategory;

/**
 * Masraf akışının iş kuralları:
 *  - draft => submit (kullanıcı) => submitted (approver bildirilir)
 *  - submitted => approve/refuse (approver)
 *  - approved => post (accounting) — Purchase Journal'a bir entry yaratır
 *
 * Reddedilen masraf tekrar submit edilebilir (draft'a benzer davranır).
 */
class ExpenseService
{
    public function submit(Expense $expense): Expense
    {
        abort_unless($expense->isEditable(), 422, 'Sadece taslak veya reddedilen masraflar gönderilebilir.');
        $expense->update([
            'status' => Expense::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'refuse_reason' => null,
        ]);

        return $expense;
    }

    public function approve(Expense $expense, User $approver): Expense
    {
        abort_unless($expense->status === Expense::STATUS_SUBMITTED, 422, 'Sadece onay bekleyen masraflar onaylanabilir.');
        $expense->update([
            'status' => Expense::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);

        return $expense;
    }

    public function refuse(Expense $expense, User $approver, string $reason): Expense
    {
        abort_unless($expense->status === Expense::STATUS_SUBMITTED, 422, 'Sadece onay bekleyen masraflar reddedilebilir.');
        $expense->update([
            'status' => Expense::STATUS_REFUSED,
            'refuse_reason' => $reason,
            'approved_by' => $approver->id,
        ]);

        return $expense;
    }

    /**
     * Onaylanan masrafı Purchase Journal'a bir yevmiye kaydı olarak
     * postalar. Borç: masraf hesabı (600xxx), Alacak: personel/şirket
     * borç hesabı (335xxx). Muhasebe modülü aktif değilse hata verir.
     */
    public function postToAccounting(Expense $expense): Expense
    {
        abort_unless($expense->status === Expense::STATUS_APPROVED, 422, 'Sadece onaylanmış masraflar postalanabilir.');

        $tenantId = $expense->tenant_id;
        $expense->loadMissing('category');

        // Önce kategorideki spesifik kodu dene, bulunmazsa 770 prefix'ini fallback yap
        $categoryCode = $expense->category?->expense_account_code;
        $expenseAccount = null;
        if ($categoryCode) {
            $expenseAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('code', $categoryCode)->first();
        }
        if ($expenseAccount === null) {
            $expenseAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)->where('code', 'like', '770%')->orderBy('code')->first();
        }
        abort_if($expenseAccount === null, 422, 'Masraf hesabı bulunamadı. Hesap planında 770xxx tanımlı olmalı.');

        $payableAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', 'like', '335%')
            ->first();
        abort_if($payableAccount === null, 422, 'Personele borçlar hesabı (335xxx) bulunamadı.');

        $journal = Journal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'purchase')
            ->first();
        abort_if($journal === null, 422, 'Alım (purchase) yevmiye defteri bulunamadı.');

        $entry = DB::transaction(function () use ($tenantId, $expense, $journal, $expenseAccount, $payableAccount) {
            $je = JournalEntry::create([
                'tenant_id' => $tenantId,
                'journal_id' => $journal->id,
                'entry_date' => $expense->expense_date,
                'reference_type' => 'expense',
                'reference_id' => $expense->id,
                'status' => 'posted',
            ]);

            JournalEntryLine::create([
                'tenant_id' => $tenantId,
                'journal_entry_id' => $je->id,
                'account_id' => $expenseAccount->id,
                'debit' => $expense->total_amount,
                'credit' => 0,
            ]);
            JournalEntryLine::create([
                'tenant_id' => $tenantId,
                'journal_entry_id' => $je->id,
                'account_id' => $payableAccount->id,
                'debit' => 0,
                'credit' => $expense->total_amount,
            ]);

            return $je;
        });

        $expense->update([
            'status' => Expense::STATUS_POSTED,
            'posted_journal_entry_id' => $entry->id,
        ]);

        return $expense;
    }

    public function computeTotal(ExpenseCategory $category, float $qty, ?float $unitPriceOverride = null): float
    {
        if ($category->isFlatRate()) {
            return round($qty * (float) $category->unit_price, 4);
        }

        return round(($unitPriceOverride ?? 0) * $qty, 4);
    }
}
