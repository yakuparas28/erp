<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hr\Database\Factories\LeaveBalanceFactory;

class LeaveBalance extends Model
{
    /** @use HasFactory<LeaveBalanceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'employee_id', 'year',
        'carried_from_previous', 'current_year_entitlement',
        'manual_adjustment', 'adjustment_reason', 'adjusted_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'carried_from_previous' => 'decimal:2',
            'current_year_entitlement' => 'decimal:2',
            'manual_adjustment' => 'decimal:2',
        ];
    }

    protected static function newFactory(): LeaveBalanceFactory
    {
        return LeaveBalanceFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
