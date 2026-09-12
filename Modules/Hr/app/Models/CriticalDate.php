<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CriticalDate extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'start_date', 'end_date', 'description', 'block_leave_requests'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'block_leave_requests' => 'boolean',
        ];
    }
}
