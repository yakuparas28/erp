<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Models\Journal;

class Payslip extends Model
{
    use BelongsToTenant;

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'tenant_id', 'payroll_period_id', 'employee_id',
        'gross_salary', 'sgk_worker', 'unemployment_worker',
        'income_tax', 'stamp_tax', 'total_deductions', 'net_salary',
        'advance_deducted',
        'sgk_employer', 'unemployment_employer', 'total_employer_cost',
        'status', 'paid_at', 'paid_from_journal_id',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:4',
            'sgk_worker' => 'decimal:4',
            'unemployment_worker' => 'decimal:4',
            'income_tax' => 'decimal:4',
            'stamp_tax' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'net_salary' => 'decimal:4',
            'advance_deducted' => 'decimal:4',
            'sgk_employer' => 'decimal:4',
            'unemployment_employer' => 'decimal:4',
            'total_employer_cost' => 'decimal:4',
            'paid_at' => 'date',
        ];
    }

    /** Personelin cebine giren para (net − avans mahsubu). */
    public function cashPayable(): string
    {
        return bcsub((string) $this->net_salary, (string) $this->advance_deducted, 4);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function paidFromJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'paid_from_journal_id');
    }
}
