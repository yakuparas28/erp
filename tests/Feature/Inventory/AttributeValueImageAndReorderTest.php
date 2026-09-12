<?php

namespace Tests\Feature\Inventory;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Models\ProductAttribute;
use Modules\Inventory\Models\ProductAttributeValue;
use Tests\TenantTestCase;

class AttributeValueImageAndReorderTest extends TenantTestCase
{
    public function test_image_can_be_uploaded_for_an_attribute_value(): void
    {
        Storage::fake('public');

        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'display_type' => 'color']);
        $value = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id]);

        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.attribute-values.image.upload', $value),
            ['image' => UploadedFile::fake()->image('swatch.png', 50, 50)],
        )->assertRedirect();

        $value->refresh();
        $this->assertNotNull($value->image_path);
        Storage::disk('public')->assertExists($value->image_path);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id]);
        $value = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id]);

        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.attribute-values.image.upload', $value),
            ['image' => UploadedFile::fake()->create('doc.pdf', 100)],
        )->assertSessionHasErrors('image');
    }

    public function test_image_can_be_removed(): void
    {
        Storage::fake('public');

        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id]);
        $value = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'image_path' => 'attribute-values/x.png']);
        Storage::disk('public')->put('attribute-values/x.png', 'fake');

        $this->actingAs($this->tenantAdmin)->delete(route('app.inventory.attribute-values.image.destroy', $value))->assertRedirect();

        $this->assertNull($value->fresh()->image_path);
        Storage::disk('public')->assertMissing('attribute-values/x.png');
    }

    public function test_attributes_can_be_reordered(): void
    {
        $first = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'sequence' => 0, 'name' => 'A']);
        $second = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'sequence' => 1, 'name' => 'B']);

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.inventory.attributes.reorder', ['attribute' => $second, 'direction' => 'up']))
            ->assertRedirect();

        $this->assertGreaterThan($second->fresh()->sequence, $first->fresh()->sequence);
    }

    public function test_values_can_be_reordered_within_the_same_attribute(): void
    {
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id]);
        $first = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'sequence' => 0, 'value' => 'A']);
        $second = ProductAttributeValue::factory()->create(['tenant_id' => $this->tenant->id, 'product_attribute_id' => $attribute->id, 'sequence' => 1, 'value' => 'B']);

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.inventory.attribute-values.reorder', ['value' => $second, 'direction' => 'up']))
            ->assertRedirect();

        $this->assertGreaterThan($second->fresh()->sequence, $first->fresh()->sequence);
    }

    public function test_reorder_at_boundary_is_a_no_op(): void
    {
        $attribute = ProductAttribute::factory()->create(['tenant_id' => $this->tenant->id, 'sequence' => 0]);

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.inventory.attributes.reorder', ['attribute' => $attribute, 'direction' => 'up']))
            ->assertRedirect();

        $this->assertSame(0, $attribute->fresh()->sequence);
    }
}
