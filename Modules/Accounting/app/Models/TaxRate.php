<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\TaxRateFactory;

class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'percentage',
        'type',
        'tax_account_id',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }

    public function taxAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'tax_account_id');
    }
}
