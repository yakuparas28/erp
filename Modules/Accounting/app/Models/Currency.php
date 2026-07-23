<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Database\Factories\CurrencyFactory;

class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'is_functional',
    ];

    protected function casts(): array
    {
        return [
            'is_functional' => 'boolean',
        ];
    }

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }
}
