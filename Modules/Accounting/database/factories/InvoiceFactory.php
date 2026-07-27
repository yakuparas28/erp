<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Invoice;
use Modules\Inventory\Models\Partner;
use Modules\Sales\Models\SalesOrder;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'partner_id' => Partner::factory(),
            'type' => 'sale',
            'source_type' => 'sales_order',
            'source_id' => SalesOrder::factory(),
            'status' => 'draft',
            'e_invoice_type' => 'kagit',
            'e_invoice_status' => 'not_sent',
        ];
    }
}
