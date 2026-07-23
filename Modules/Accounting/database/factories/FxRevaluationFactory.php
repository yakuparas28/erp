<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\FxRevaluation;
use Modules\Accounting\Models\Invoice;

/**
 * @extends Factory<FxRevaluation>
 */
class FxRevaluationFactory extends Factory
{
    protected $model = FxRevaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invoice_id' => Invoice::factory(),
            'payment_id' => null,
            'type' => 'unrealized',
            'difference_amount' => '0.0000',
            'revaluation_date' => now()->toDateString(),
        ];
    }
}
