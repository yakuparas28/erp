<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\TenantModuleActivation;
use Tests\TenantTestCase;

/**
 * Web tarafında (sidebar + route middleware) lisans kısıtlaması gerçekten
 * çalışıyor mu? TenantTestCase varsayılan olarak tüm modülleri aktive eder;
 * bu test önce hepsini kapatıp lisansa göre tek tek açar.
 */
class LicenseModuleAccessTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TenantModuleActivation::where('tenant_id', $this->tenant->id)->delete();
    }

    public function test_sales_screen_is_forbidden_when_sales_module_is_not_activated(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get('/app/sales/orders')
            ->assertForbidden();
    }

    public function test_purchase_screen_is_forbidden_when_purchase_module_is_not_activated(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get('/app/purchase/orders')
            ->assertForbidden();
    }

    public function test_accounting_screen_is_forbidden_when_accounting_module_is_not_activated(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get('/app/accounting/accounts')
            ->assertForbidden();
    }

    public function test_inventory_screen_always_works_because_module_is_core(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get('/app/inventory/products')
            ->assertOk();
    }

    public function test_sales_screen_works_after_activating_sales_module(): void
    {
        TenantModuleActivation::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => Module::where('key', 'sales')->firstOrFail()->id,
            'is_active' => true,
            'source' => 'package',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app/sales/orders')
            ->assertOk();
    }

    public function test_sidebar_hides_disabled_module_links(): void
    {
        $response = $this->actingAs($this->tenantAdmin)
            ->get('/app')
            ->assertOk();

        $response->assertDontSee(route('app.sales.orders.index'));
        $response->assertDontSee(route('app.purchase.orders.index'));
        $response->assertDontSee(route('app.accounting.accounts.index'));
    }

    public function test_sidebar_shows_activated_module_link(): void
    {
        TenantModuleActivation::create([
            'tenant_id' => $this->tenant->id,
            'module_id' => Module::where('key', 'sales')->firstOrFail()->id,
            'is_active' => true,
            'source' => 'package',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get('/app')
            ->assertOk()
            ->assertSee(route('app.sales.orders.index'));
    }
}
