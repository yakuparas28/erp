<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveHourConfig extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'department_id', 'daily_work_hours', 'monthly_leave_hours'];

    protected function casts(): array
    {
        return [
            'daily_work_hours' => 'decimal:2',
            'monthly_leave_hours' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
