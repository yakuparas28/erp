<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Hr\Database\Factories\LeaveTypeFactory;

class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'key', 'name',
        'deducts_from_balance', 'requires_document', 'requires_second_level',
        'unit', 'max_days_per_year', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'deducts_from_balance' => 'boolean',
            'requires_document' => 'boolean',
            'requires_second_level' => 'boolean',
            'is_active' => 'boolean',
            'max_days_per_year' => 'integer',
        ];
    }

    protected static function newFactory(): LeaveTypeFactory
    {
        return LeaveTypeFactory::new();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
