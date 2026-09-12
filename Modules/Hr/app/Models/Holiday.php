<?php

namespace Modules\Hr\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'date', 'name', 'is_recurring_yearly'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring_yearly' => 'boolean',
        ];
    }
}
