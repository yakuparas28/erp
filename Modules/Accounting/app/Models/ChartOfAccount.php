<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Database\Factories\ChartOfAccountFactory;

class ChartOfAccount extends Model
{
    /** @use HasFactory<ChartOfAccountFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
    ];

    protected static function newFactory(): ChartOfAccountFactory
    {
        return ChartOfAccountFactory::new();
    }
}
