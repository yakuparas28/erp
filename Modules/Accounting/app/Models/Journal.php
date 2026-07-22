<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Database\Factories\JournalFactory;

class Journal extends Model
{
    /** @use HasFactory<JournalFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
    ];

    protected static function newFactory(): JournalFactory
    {
        return JournalFactory::new();
    }
}
