<?php

namespace Modules\Expenses\Models;

use App\Concerns\HasApproval;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hr\Models\Employee;

class Expense extends Model
{
    use BelongsToTenant, HasApproval;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REFUSED = 'refused';

    public const STATUS_POSTED = 'posted';

    public const STATUS_PAID = 'paid';

    public const PAID_BY_EMPLOYEE = 'employee';

    public const PAID_BY_COMPANY = 'company';

    protected $fillable = [
        'tenant_id', 'employee_id', 'expense_category_id',
        'description', 'expense_date', 'qty', 'unit_price', 'total_amount',
        'currency_code', 'paid_by', 'status', 'receipt_path', 'notes',
        'reference', 'sales_order_id', 'posted_journal_entry_id',
        'submitted_at', 'approved_at', 'approved_by', 'refuse_reason',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REFUSED], true);
    }
}
