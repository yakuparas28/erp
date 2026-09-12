<?php

namespace Tests\Feature\Inventory;

use App\Models\Tenant;
use App\Models\User;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductCategory;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Tests\TenantTestCase;

class CategoryScreensTest extends TenantTestCase
{
    public function test_categories_index_lists_existing_categories(): void
    {
        ProductCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Elektronik']);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/categories')
            ->assertOk()
            ->assertSee('Elektronik');
    }

    public function test_new_root_category_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/categories', [
            'name' => 'Gıda',
            'parent_id' => null,
        ])->assertRedirect(route('app.inventory.categories.index'));

        $this->assertDatabaseHas('product_categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Gıda',
            'parent_id' => null,
        ]);
    }

    public function test_sub_category_can_be_created_under_a_parent(): void
    {
        $parent = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alkolsüz İçecekler']);

        $this->actingAs($this->tenantAdmin)->post('/app/inventory/categories', [
            'name' => 'Su',
            'parent_id' => $parent->id,
        ]);

        $this->assertDatabaseHas('product_categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Su',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)
            ->patch("/app/inventory/categories/{$category->id}", [
                'name' => $category->name,
                'parent_id' => $category->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_category_with_children_cannot_be_deleted(): void
    {
        $parent = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        ProductCategory::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $parent->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/categories/{$parent->id}")
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('product_categories', ['id' => $parent->id]);
    }

    public function test_category_used_by_products_cannot_be_deleted(): void
    {
        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id]);
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/categories/{$category->id}")
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    public function test_unused_leaf_category_can_be_deleted(): void
    {
        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/categories/{$category->id}")
            ->assertRedirect(route('app.inventory.categories.index'));

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }

    public function test_categories_are_scoped_to_current_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCategory = ProductCategory::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/categories/{$otherCategory->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('product_categories', ['id' => $otherCategory->id]);
    }

    public function test_user_without_permission_cannot_access_categories_page(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/inventory/categories')
            ->assertForbidden();
    }
}
