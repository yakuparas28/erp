<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'year', 'month', 'status',
        'total_gross', 'total_deductions', 'total_net', 'total_employer_cost',
        'created_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'total_gross' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'total_net' => 'decimal:4',
            'total_employer_cost' => 'decimal:4',
            'posted_at' => 'date',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
