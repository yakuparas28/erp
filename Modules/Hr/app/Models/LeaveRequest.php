<?php

namespace Modules\Hr\Models;

use App\Concerns\HasApproval;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Hr\Database\Factories\LeaveRequestFactory;

class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use BelongsToTenant, HasApproval, HasFactory;

    protected $fillable = [
        'tenant_id', 'employee_id', 'leave_type_id',
        'start_date', 'end_date', 'start_time', 'end_time',
        'half_day_type', 'total_days', 'return_to_work_date',
        'reason', 'travel_allowance_requested', 'ticket_no', 'document_path',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'return_to_work_date' => 'date',
            'total_days' => 'decimal:2',
            'travel_allowance_requested' => 'boolean',
        ];
    }

    protected static function newFactory(): LeaveRequestFactory
    {
        return LeaveRequestFactory::new();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
