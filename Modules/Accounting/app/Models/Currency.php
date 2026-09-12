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
        'symbol',
        'position',
        'rounding',
        'is_functional',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'is_functional' => 'boolean',
            'active' => 'boolean',
            'rounding' => 'decimal:6',
        ];
    }

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }

    /**
     * Odoo `decimal_places` compute'u: rounding faktöründen türetilir.
     * rounding=0.01 → 2, rounding=0.0001 → 4, rounding=1 → 0.
     */
    public function getDecimalPlacesAttribute(): int
    {
        $rounding = (float) $this->rounding;

        if ($rounding <= 0 || $rounding >= 1) {
            return 0;
        }

        return (int) round(-log10($rounding));
    }
}
