<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Hr\Database\Factories\EmployeeFactory;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'department_id', 'manager_id',
        'first_name', 'last_name', 'title',
        'hire_date', 'birth_date', 'termination_date',
        'national_id', 'phone', 'mobile',
        'is_active', 'annual_leave_balance', 'second_level_approval_required', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'birth_date' => 'date',
            'termination_date' => 'date',
            'is_active' => 'boolean',
            'second_level_approval_required' => 'boolean',
            'annual_leave_balance' => 'decimal:2',
        ];
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }
}
