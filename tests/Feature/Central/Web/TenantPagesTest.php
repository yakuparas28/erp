<?php

namespace Tests\Feature\Central\Web;

use App\Models\LicensePackage;
use App\Models\Module;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantModuleActivation;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TenantPagesTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class, RoleSeeder::class, NotificationTemplateSeeder::class]);
        $this->admin = SuperAdmin::factory()->create();
    }

    public function test_tenants_index_lists_tenants(): void
    {
        Tenant::factory()->create(['name' => 'Acme Lojistik AŞ']);

        $this->actingAs($this->admin, 'central_web')
            ->get('/central/tenants')
            ->assertOk()
            ->assertSee('Acme Lojistik AŞ');
    }

    public function test_tenant_can_be_created_from_the_panel(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin, 'central_web')->post('/central/tenants', [
            'name' => 'Panel Firması',
            'accounting_mode' => 'anglo_saxon',
            'admin_name' => 'Panel Yöneticisi',
            'admin_email' => 'panel@ornek.test',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', ['name' => 'Panel Firması', 'accounting_mode' => 'anglo_saxon']);
        $this->assertNotNull(Activity::where('description', 'tenant.created')->first());
    }

    public function test_tenant_can_be_updated_from_the_panel(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Eski Ad']);

        $this->actingAs($this->admin, 'central_web')
            ->put("/central/tenants/{$tenant->id}", [
                'name' => 'Yeni Ad',
                'accounting_mode' => 'continental',
            ])
            ->assertRedirect();

        $this->assertSame('Yeni Ad', $tenant->fresh()->name);
        $this->assertNotNull(Activity::where('description', 'tenant.updated')->first());
    }

    public function test_show_page_renders_modules_and_subscription_form(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin, 'central_web')
            ->get("/central/tenants/{$tenant->id}")
            ->assertOk()
            ->assertSee('Lisans Paketi')
            ->assertSee('Envanter')
            ->assertSee('Muhasebe');
    }

    public function test_subscription_can_be_assigned_from_the_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $package = LicensePackage::where('name', 'Standart')->firstOrFail();

        $this->actingAs($this->admin, 'central_web')
            ->post("/central/tenants/{$tenant->id}/subscription", [
                'license_package_id' => $package->id,
                'status' => 'active',
                'starts_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(
            6,
            TenantModuleActivation::where('tenant_id', $tenant->id)->where('is_active', true)->count(),
        );
    }

    public function test_module_can_be_toggled_and_core_is_protected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin, 'central_web')
            ->post("/central/tenants/{$tenant->id}/modules/accounting")
            ->assertRedirect();

        $this->assertTrue(
            TenantModuleActivation::where('tenant_id', $tenant->id)
                ->where('module_id', Module::where('key', 'accounting')->firstOrFail()->id)
                ->where('is_active', true)
                ->exists(),
        );

        $this->actingAs($this->admin, 'central_web')
            ->from("/central/tenants/{$tenant->id}")
            ->delete("/central/tenants/{$tenant->id}/modules/inventory")
            ->assertRedirect("/central/tenants/{$tenant->id}")
            ->assertSessionHasErrors('module');
    }
}
