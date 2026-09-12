<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Database\QueryException;
use Modules\Inventory\Models\OperationType;
use Modules\Inventory\Models\Warehouse;
use Tests\TenantTestCase;

class OperationTypeTest extends TenantTestCase
{
    public function test_index_lists_operation_types(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Main']);
        OperationType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'RCV',
            'name' => 'Mal Kabul',
            'type' => 'incoming',
            'sequence_prefix' => 'WH/IN',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/operation-types')
            ->assertOk()
            ->assertSee('Mal Kabul')
            ->assertSee('WH/IN');
    }

    public function test_operation_type_can_be_created(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/operation-types', [
            'warehouse_id' => $warehouse->id,
            'code' => 'DLV',
            'name' => 'Sevkiyat',
            'type' => 'outgoing',
            'sequence_prefix' => 'WH/OUT',
        ])->assertRedirect(route('app.inventory.operation-types.index'));

        $this->assertDatabaseHas('operation_types', [
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'DLV',
            'type' => 'outgoing',
        ]);
    }

    public function test_code_must_be_unique_per_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        OperationType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'DUP',
        ]);

        $this->expectException(QueryException::class);

        OperationType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'DUP',
        ]);
    }

    public function test_type_must_be_valid(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/operation-types', [
            'warehouse_id' => $warehouse->id,
            'code' => 'BAD',
            'name' => 'Bad',
            'type' => 'invalid',
        ])->assertSessionHasErrors('type');
    }

    public function test_operation_type_can_be_updated_and_deleted(): void
    {
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $type = OperationType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'X',
        ]);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/operation-types/{$type->id}", [
            'warehouse_id' => $warehouse->id,
            'code' => 'X',
            'name' => 'Yeni',
            'type' => 'internal',
        ])->assertRedirect(route('app.inventory.operation-types.index'));

        $this->assertDatabaseHas('operation_types', ['id' => $type->id, 'name' => 'Yeni']);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/operation-types/{$type->id}")
            ->assertRedirect(route('app.inventory.operation-types.index'));

        $this->assertDatabaseMissing('operation_types', ['id' => $type->id]);
    }

    public function test_user_without_permission_cannot_access(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/inventory/operation-types')
            ->assertForbidden();
    }
}
