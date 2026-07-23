<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Currency;
use Modules\Accounting\Models\ExchangeRate;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'currency_id' => Currency::factory(),
            'rate_date' => now()->toDateString(),
            'buy_rate' => '32.500000',
            'sell_rate' => '32.600000',
            'source' => 'manual',
        ];
    }
}
