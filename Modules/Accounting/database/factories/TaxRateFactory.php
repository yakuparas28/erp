<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\TaxRate;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'KDV %20',
            'percentage' => '20.00',
            'type' => 'sale',
            'tax_account_id' => ChartOfAccount::factory(),
        ];
    }
}
