<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Hr\Database\Factories\DepartmentFactory;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'manager_employee_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
