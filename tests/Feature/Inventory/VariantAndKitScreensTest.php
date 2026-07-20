<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Modules\Inventory\Models\ProductKitComponent;
use Modules\Inventory\Models\ProductTemplate;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Tests\TenantTestCase;

class VariantAndKitScreensTest extends TenantTestCase
{
    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->tenantAdmin);

        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);
    }

    public function test_products_page_shows_kit_badge_and_components_button(): void
    {
        $componentProduct = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Bileşen']);
        $kit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Set Ürünü', 'is_kit' => true]);

        $this->get('/app/inventory/products')
            ->assertOk()
            ->assertSee('Set Ürünü')
            ->assertSee(__('Kit'));
    }

    public function test_kit_component_can_be_added_and_removed_via_http(): void
    {
        $componentProduct = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        $kit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'is_kit' => true]);

        $this->post("/app/inventory/products/{$kit->id}/kit-components", [
            'component_product_id' => $componentProduct->id,
            'qty' => '2',
        ])->assertRedirect();

        $this->assertDatabaseHas('product_kit_components', [
            'kit_product_id' => $kit->id,
            'component_product_id' => $componentProduct->id,
        ]);

        $component = ProductKitComponent::firstOrFail();

        $this->delete("/app/inventory/kit-components/{$component->id}")->assertRedirect();

        $this->assertDatabaseMissing('product_kit_components', ['id' => $component->id]);
    }

    public function test_nested_kit_component_is_rejected_via_http(): void
    {
        $nestedKit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'is_kit' => true]);
        $kit = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'is_kit' => true]);

        $this->from('/app/inventory/products')->post("/app/inventory/products/{$kit->id}/kit-components", [
            'component_product_id' => $nestedKit->id,
            'qty' => '1',
        ])->assertRedirect('/app/inventory/products')->assertSessionHasErrors('component_product_id');
    }

    public function test_templates_page_lists_templates_and_attributes(): void
    {
        ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'T-Shirt']);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Renk']);
        ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'value' => 'Kırmızı']);

        $this->get('/app/inventory/templates')
            ->assertOk()
            ->assertSee('T-Shirt')
            ->assertSee('Renk')
            ->assertSee('Kırmızı');
    }

    public function test_attaching_attribute_generates_variants_and_shows_on_template_page(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'T-Shirt']);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Renk', 'creation_mode' => 'instant']);
        ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'value' => 'Kırmızı']);
        ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'value' => 'Mavi']);

        $this->post("/app/inventory/templates/{$template->id}/attributes", [
            'product_attribute_id' => $attribute->id,
        ])->assertRedirect();

        $this->assertSame(2, Product::where('product_template_id', $template->id)->count());

        $this->get("/app/inventory/templates/{$template->id}")
            ->assertOk()
            ->assertSee('T-Shirt Kırmızı')
            ->assertSee('T-Shirt Mavi');
    }

    public function test_products_index_groups_variants_under_their_template_instead_of_listing_each_row(): void
    {
        $template = ProductTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pamuklu Tişört']);
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Renk', 'creation_mode' => 'instant']);
        ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'value' => 'Kırmızı']);
        ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'value' => 'Mavi']);

        $this->post("/app/inventory/templates/{$template->id}/attributes", [
            'product_attribute_id' => $attribute->id,
        ])->assertRedirect();

        $response = $this->get('/app/inventory/products')->assertOk();

        $response->assertSee('Pamuklu Tişört');
        $response->assertSee(route('app.inventory.templates.show', $template), false);
        $response->assertDontSee('Pamuklu Tişört Kırmızı');
        $response->assertDontSee('Pamuklu Tişört Mavi');
    }

    public function test_operator_without_permission_cannot_manage_products(): void
    {
        auth('web')->logout();
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator)->get('/app/inventory/templates')->assertForbidden();
    }
}
