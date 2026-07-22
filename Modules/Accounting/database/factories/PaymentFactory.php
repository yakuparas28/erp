<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Payment;
use Modules\Inventory\Models\Partner;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'partner_id' => Partner::factory(),
            'journal_id' => Journal::factory(),
            'amount' => '100.0000',
            'payment_date' => now()->toDateString(),
        ];
    }
}
