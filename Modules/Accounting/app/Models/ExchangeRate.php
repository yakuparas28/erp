<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\ExchangeRateFactory;

class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'currency_id',
        'rate_date',
        'buy_rate',
        'sell_rate',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'buy_rate' => 'decimal:6',
            'sell_rate' => 'decimal:6',
        ];
    }

    protected static function newFactory(): ExchangeRateFactory
    {
        return ExchangeRateFactory::new();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Odoo'nun `inverse_company_rate` compute'una karşılık: 1 döviz kaç TL
     * yerine 1 TL kaç dövize denk geliyor. buy_rate üzerinden hesaplanır
     * (TCMB'de alış temel kurdur).
     */
    public function getInverseBuyRateAttribute(): string
    {
        return bcdiv('1', (string) $this->buy_rate, 6);
    }
}
