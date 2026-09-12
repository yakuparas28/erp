<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Tests\TenantTestCase;

class UomScreensTest extends TenantTestCase
{
    public function test_uom_index_lists_categories_and_units(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ağırlık']);
        Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $category->id,
            'name' => 'Kilogram',
            'factor' => '1.000000',
            'is_reference' => true,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/uoms')
            ->assertOk()
            ->assertSee('Ağırlık')
            ->assertSee('Kilogram');
    }

    public function test_new_uom_category_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->post('/app/inventory/uom-categories', ['name' => 'Hacim'])
            ->assertRedirect();

        $this->assertDatabaseHas('uom_categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Hacim',
        ]);
    }

    public function test_new_uom_can_be_created_in_a_category(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/uoms', [
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'factor' => '0.001000',
            'is_reference' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('uoms', [
            'uom_category_id' => $category->id,
            'name' => 'Gram',
            'factor' => '0.001000',
            'is_reference' => false,
        ]);
    }

    public function test_reference_unit_is_unique_per_category(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $category->id,
            'is_reference' => true,
            'factor' => '1',
        ]);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/uoms', [
            'uom_category_id' => $category->id,
            'name' => 'Second Reference',
            'factor' => '1.000000',
            'is_reference' => '1',
        ])->assertSessionHasErrors('is_reference');
    }

    public function test_uom_factor_must_be_positive(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/uoms', [
            'uom_category_id' => $category->id,
            'name' => 'Bad',
            'factor' => '0',
        ])->assertSessionHasErrors('factor');
    }

    public function test_uom_used_by_a_product_cannot_be_deleted(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $category->id,
            'is_reference' => false,
        ]);
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/uoms/{$uom->id}")
            ->assertSessionHasErrors('uom');

        $this->assertDatabaseHas('uoms', ['id' => $uom->id]);
    }

    public function test_reference_uom_cannot_be_deleted(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $ref = Uom::factory()->create([
            'tenant_id' => $this->tenant->id,
            'uom_category_id' => $category->id,
            'is_reference' => true,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/uoms/{$ref->id}")
            ->assertSessionHasErrors('uom');
    }

    public function test_category_with_units_cannot_be_deleted(): void
    {
        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/uom-categories/{$category->id}")
            ->assertSessionHasErrors('category');
    }

    public function test_user_without_permission_cannot_access_uoms_page(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/inventory/uoms')
            ->assertForbidden();
    }
}
