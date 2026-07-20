<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

class ServiceProductCostMethodTest extends TenantTestCase
{
    public function test_service_product_must_use_standard_cost_method(): void
    {
        $this->actingAs($this->tenantAdmin);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);

        try {
            Product::factory()->create([
                'tenant_id' => $this->tenant->id,
                'uom_id' => $uom->id,
                'product_type' => 'service',
                'cost_method' => 'fifo',
            ]);
            $this->fail('422 bekleniyordu');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_service_product_with_standard_cost_method_is_allowed(): void
    {
        $this->actingAs($this->tenantAdmin);
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_id' => $uom->id,
            'product_type' => 'service',
            'cost_method' => 'standard',
        ]);

        $this->assertSame('service', $product->product_type);
    }
}
