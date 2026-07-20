<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductBarcode;
use Modules\Inventory\Models\Uom;
use Modules\Inventory\Models\UomCategory;
use Tests\TenantTestCase;

class DeltaSyncTest extends TenantTestCase
{
    private User $operator;

    private Uom $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        // Mobil senaryo Sanctum token'ıyla kimlik doğrular; web oturumu
        // (actingAs) kullanılmaz ki guest/token testleri gerçekçi kalsın.
        $this->operator = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $this->operator->assignRole('Warehouse Operator');

        $category = UomCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->unit = Uom::factory()->create(['tenant_id' => $this->tenant->id, 'uom_category_id' => $category->id, 'is_reference' => true]);
    }

    private function token(): string
    {
        return $this->operator->createToken('mobile')->plainTextToken;
    }

    public function test_first_sync_without_cursor_returns_full_catalog(): void
    {
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Ürün A']);
        Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Ürün B']);

        $response = $this->withToken($this->token())->getJson('/api/inventory/sync/catalog')->assertOk();

        $response->assertJsonCount(2, 'products');
        $this->assertNotEmpty($response->json('next_cursor'));
    }

    public function test_cursor_only_returns_products_changed_after_it(): void
    {
        $old = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Eski Ürün']);

        $firstResponse = $this->withToken($this->token())->getJson('/api/inventory/sync/catalog')->assertOk();
        $cursor = $firstResponse->json('next_cursor');

        $newProduct = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id, 'name' => 'Yeni Ürün']);

        $secondResponse = $this->withToken($this->token())
            ->getJson('/api/inventory/sync/catalog?cursor='.urlencode($cursor))
            ->assertOk();

        $secondResponse->assertJsonCount(1, 'products');
        $this->assertSame($newProduct->id, $secondResponse->json('products.0.id'));
    }

    public function test_same_second_updates_are_not_skipped(): void
    {
        $first = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        $sameTimestamp = $first->updated_at;
        $second = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        $second->forceFill(['updated_at' => $sameTimestamp])->saveQuietly();

        $response = $this->withToken($this->token())->getJson('/api/inventory/sync/catalog')->assertOk();
        $cursor = $response->json('next_cursor');
        $this->assertSame(2, count($response->json('products')));

        // Cursor bu iki kaydı da kapsadıktan sonra tekrar sorgulanınca hiçbiri kaçmamalı, boş dönmeli.
        $again = $this->withToken($this->token())->getJson('/api/inventory/sync/catalog?cursor='.urlencode($cursor))->assertOk();
        $this->assertSame(0, count($again->json('products')));
    }

    public function test_catalog_includes_barcodes_and_uoms(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'uom_id' => $this->unit->id]);
        ProductBarcode::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'barcode' => '1234567890123']);

        $response = $this->withToken($this->token())->getJson('/api/inventory/sync/catalog')->assertOk();

        $response->assertJsonFragment(['barcode' => '1234567890123']);
        $this->assertNotEmpty($response->json('uoms'));
    }

    public function test_tenant_admin_without_operator_role_can_also_view_catalog(): void
    {
        $token = $this->tenantAdmin->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/inventory/sync/catalog')->assertOk();
    }

    public function test_guest_cannot_access_sync_catalog(): void
    {
        $this->getJson('/api/inventory/sync/catalog')->assertUnauthorized();
    }
}
