<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\StorageCategory;
use Modules\Inventory\Models\Warehouse;
use Tests\TenantTestCase;

class StorageCategoryScreensTest extends TenantTestCase
{
    public function test_index_lists_categories(): void
    {
        StorageCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ambar Rafı']);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/storage-categories')
            ->assertOk()
            ->assertSee('Ambar Rafı');
    }

    public function test_new_category_can_be_created(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/storage-categories', [
            'name' => 'Soğuk Depo',
            'max_weight' => '500',
            'allow_new_product' => 'same',
        ])->assertRedirect(route('app.inventory.storage-categories.index'));

        $this->assertDatabaseHas('storage_categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Soğuk Depo',
            'allow_new_product' => 'same',
        ]);
    }

    public function test_allow_new_product_must_be_valid(): void
    {
        $this->actingAs($this->tenantAdmin)->post('/app/inventory/storage-categories', [
            'name' => 'Bad',
            'max_weight' => '100',
            'allow_new_product' => 'wrong',
        ])->assertSessionHasErrors('allow_new_product');
    }

    public function test_category_can_be_updated(): void
    {
        $category = StorageCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)->patch("/app/inventory/storage-categories/{$category->id}", [
            'name' => 'Yeni İsim',
            'max_weight' => '2000',
            'allow_new_product' => 'empty',
        ])->assertRedirect(route('app.inventory.storage-categories.index'));

        $this->assertDatabaseHas('storage_categories', ['id' => $category->id, 'name' => 'Yeni İsim', 'allow_new_product' => 'empty']);
    }

    public function test_category_used_by_locations_cannot_be_deleted(): void
    {
        $category = StorageCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        Location::factory()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $warehouse->id,
            'storage_category_id' => $category->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/storage-categories/{$category->id}")
            ->assertSessionHasErrors('storage_category');

        $this->assertDatabaseHas('storage_categories', ['id' => $category->id]);
    }

    public function test_unused_category_can_be_deleted(): void
    {
        $category = StorageCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)
            ->delete("/app/inventory/storage-categories/{$category->id}")
            ->assertRedirect(route('app.inventory.storage-categories.index'));

        $this->assertDatabaseMissing('storage_categories', ['id' => $category->id]);
    }

    public function test_user_without_permission_cannot_access(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)
            ->get('/app/inventory/storage-categories')
            ->assertForbidden();
    }
}
