<?php

namespace Tests\Feature\Inventory;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Tests\TenantTestCase;

class ProductImageTest extends TenantTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $uomCategory = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $uom = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $uomCategory->id, 'is_reference' => true]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $uom->id]);
    }

    public function test_image_can_be_uploaded_for_a_product(): void
    {
        Storage::fake('public');

        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.products.image.upload', $this->product),
            ['image' => UploadedFile::fake()->image('cover.png', 200, 200)],
        )->assertRedirect();

        $this->product->refresh();
        $this->assertNotNull($this->product->image_path);
        Storage::disk('public')->assertExists($this->product->image_path);
    }

    public function test_upload_rejects_non_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->tenantAdmin)->post(
            route('app.inventory.products.image.upload', $this->product),
            ['image' => UploadedFile::fake()->create('brochure.pdf', 100)],
        )->assertSessionHasErrors('image');
    }

    public function test_image_can_be_removed(): void
    {
        Storage::fake('public');
        $this->product->update(['image_path' => 'products/x.png']);
        Storage::disk('public')->put('products/x.png', 'fake');

        $this->actingAs($this->tenantAdmin)
            ->delete(route('app.inventory.products.image.destroy', $this->product))
            ->assertRedirect();

        $this->assertNull($this->product->fresh()->image_path);
        Storage::disk('public')->assertMissing('products/x.png');
    }
}
