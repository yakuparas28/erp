<?php

namespace Tests\Feature;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantTestItem extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_test_items';

    protected $fillable = ['name', 'tenant_id'];
}

class BelongsToTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenant_test_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function test_queries_are_scoped_to_the_authenticated_users_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        TenantTestItem::create(['name' => 'A kaydı', 'tenant_id' => $tenantA->id]);
        TenantTestItem::create(['name' => 'B kaydı', 'tenant_id' => $tenantB->id]);

        $this->actingAs(User::factory()->for($tenantA)->create());

        $this->assertSame(['A kaydı'], TenantTestItem::pluck('name')->all());
    }

    public function test_tenant_id_is_filled_automatically_on_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs(User::factory()->for($tenant)->create());

        $item = TenantTestItem::create(['name' => 'Otomatik']);

        $this->assertSame($tenant->id, $item->tenant_id);
    }

    public function test_scope_is_not_applied_without_an_authenticated_user(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        TenantTestItem::create(['name' => 'A kaydı', 'tenant_id' => $tenantA->id]);
        TenantTestItem::create(['name' => 'B kaydı', 'tenant_id' => $tenantB->id]);

        $this->assertCount(2, TenantTestItem::all());
    }

    public function test_without_global_scope_returns_all_tenants_records(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        TenantTestItem::create(['name' => 'A kaydı', 'tenant_id' => $tenantA->id]);
        TenantTestItem::create(['name' => 'B kaydı', 'tenant_id' => $tenantB->id]);

        $this->actingAs(User::factory()->for($tenantA)->create());

        $this->assertCount(2, TenantTestItem::withoutGlobalScope(TenantScope::class)->get());
    }

    public function test_tenant_relation_resolves(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs(User::factory()->for($tenant)->create());

        $item = TenantTestItem::create(['name' => 'İlişkili']);

        $this->assertTrue($item->tenant->is($tenant));
    }
}
