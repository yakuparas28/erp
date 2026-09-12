<?php

namespace Tests\Feature\Inventory;

use App\Models\Tenant;
use App\Models\User;
use Modules\Inventory\Models\PackageType;
use Tests\TenantTestCase;

class PackageTypeScreensTest extends TenantTestCase
{
    public function test_index_lists_existing_types(): void
    {
        PackageType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Küçük Kutu']);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/package-types')
            ->assertOk()
            ->assertSee('Küçük Kutu');
    }

    public function test_new_package_type_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/package-types', [
            'name' => 'Standart Palet',
            'barcode' => 'PAL-STD',
            'packaging_length' => '120',
            'width' => '80',
            'height' => '15',
            'max_weight' => '1000',
        ])->assertRedirect(route('app.inventory.package-types.index'));

        $this->assertDatabaseHas('package_types', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Standart Palet',
            'barcode' => 'PAL-STD',
        ]);
    }

    public function test_name_is_unique_per_tenant(): void
    {
        PackageType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kutu']);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/package-types', [
            'name' => 'Kutu',
            'packaging_length' => '10',
            'width' => '10',
            'height' => '10',
            'max_weight' => '5',
        ])->assertSessionHasErrors('name');
    }

    public function test_package_type_can_be_updated(): void
    {
        $type = PackageType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kutu']);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/package-types/{$type->id}", [
            'name' => 'Büyük Kutu',
            'packaging_length' => '50',
            'width' => '40',
            'height' => '30',
            'max_weight' => '20',
        ])->assertRedirect(route('app.inventory.package-types.index'));

        $this->assertDatabaseHas('package_types', ['id' => $type->id, 'name' => 'Büyük Kutu']);
    }

    public function test_package_type_can_be_deleted(): void
    {
        $type = PackageType::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/package-types/{$type->id}")
            ->assertRedirect(route('app.inventory.package-types.index'));

        $this->assertDatabaseMissing('package_types', ['id' => $type->id]);
    }

    public function test_package_types_are_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherType = PackageType::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/package-types/{$otherType->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('package_types', ['id' => $otherType->id]);
    }

    public function test_user_without_permission_cannot_access(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/inventory/package-types')
            ->assertForbidden();
    }
}
