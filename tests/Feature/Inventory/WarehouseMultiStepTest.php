<?php

namespace Tests\Feature\Inventory;

use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Warehouse;
use Tests\TenantTestCase;

class WarehouseMultiStepTest extends TenantTestCase
{
    public function test_warehouse_can_be_configured_for_two_step_reception(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $input = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id, 'name' => 'Alım Bekliyor']);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/warehouses/{$warehouse->id}", [
            'name' => $warehouse->name,
            'reception_steps' => 'two_step',
            'delivery_steps' => 'one_step',
            'input_location_id' => $input->id,
        ])->assertRedirect(route('app.inventory.warehouses.index'));

        $warehouse->refresh();
        $this->assertSame('two_step', $warehouse->reception_steps);
        $this->assertSame($input->id, $warehouse->input_location_id);
    }

    public function test_three_step_reception_requires_quality_location(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $input = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/warehouses/{$warehouse->id}", [
            'name' => $warehouse->name,
            'reception_steps' => 'three_step',
            'delivery_steps' => 'one_step',
            'input_location_id' => $input->id,
        ])->assertSessionHasErrors('quality_location_id');
    }

    public function test_two_step_reception_requires_input_location(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/warehouses/{$warehouse->id}", [
            'name' => $warehouse->name,
            'reception_steps' => 'two_step',
            'delivery_steps' => 'one_step',
        ])->assertSessionHasErrors('input_location_id');
    }

    public function test_three_step_delivery_requires_pack_and_output_locations(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $output = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $warehouse->id]);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/warehouses/{$warehouse->id}", [
            'name' => $warehouse->name,
            'reception_steps' => 'one_step',
            'delivery_steps' => 'three_step',
            'output_location_id' => $output->id,
        ])->assertSessionHasErrors('pack_location_id');
    }

    public function test_location_from_other_warehouse_is_rejected(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherWarehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $foreignInput = Location::factory()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $otherWarehouse->id]);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/warehouses/{$warehouse->id}", [
            'name' => $warehouse->name,
            'reception_steps' => 'two_step',
            'delivery_steps' => 'one_step',
            'input_location_id' => $foreignInput->id,
        ])->assertSessionHasErrors('input_location_id');
    }
}
