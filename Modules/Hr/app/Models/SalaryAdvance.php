<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Journal;

class SalaryAdvance extends Model
{
    use BelongsToTenant;

    public const STATUS_OUTSTANDING = 'outstanding';

    public const STATUS_DEDUCTED = 'deducted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'employee_id', 'amount', 'granted_at',
        'paid_from_journal_id', 'status', 'deducted_in_payslip_id',
        'deducted_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'granted_at' => 'date',
            'deducted_at' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paidFromJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'paid_from_journal_id');
    }

    public function deductedInPayslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'deducted_in_payslip_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
