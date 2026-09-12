<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveHourConfig extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'department_id', 'is_active',
        'daily_work_hours', 'monthly_leave_hours', 'min_hours', 'negative_balance_policy',
    ];

    protected function casts(): array
    {
        return [
            'daily_work_hours' => 'decimal:2',
            'monthly_leave_hours' => 'decimal:2',
            'min_hours' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
