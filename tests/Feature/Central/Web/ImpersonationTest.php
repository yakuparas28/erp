<?php

namespace Tests\Feature\Central\Web;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::factory()->create();
    }

    public function test_super_admin_can_impersonate_a_tenant_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($this->admin, 'central_web')
            ->post("/central/tenants/{$tenant->id}/users/{$user->id}/impersonate")
            ->assertRedirect('/app');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertAuthenticatedAs($this->admin, 'central_web');
        $this->assertNotNull(Activity::where('description', 'user.impersonated')->first());
    }

    public function test_cannot_impersonate_a_user_of_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignUser = User::factory()->for(Tenant::factory())->create();

        $this->actingAs($this->admin, 'central_web')
            ->post("/central/tenants/{$tenant->id}/users/{$foreignUser->id}/impersonate")
            ->assertNotFound();

        $this->assertGuest('web');
    }

    public function test_guests_cannot_impersonate(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->post("/central/tenants/{$tenant->id}/users/{$user->id}/impersonate")
            ->assertRedirect('/login');
    }

    public function test_leaving_impersonation_returns_to_central_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->actingAs($this->admin, 'central_web')->actingAs($user, 'web');

        $this->post('/impersonation/leave')
            ->assertRedirect("/central/tenants/{$tenant->id}");

        $this->assertGuest('web');
        $this->assertAuthenticatedAs($this->admin, 'central_web');
    }

    public function test_impersonation_banner_is_visible_in_tenant_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create(['name' => 'Görülen Kişi']);

        $this->seed([ModuleSeeder::class]);

        $this->actingAs($this->admin, 'central_web')->actingAs($user, 'web')
            ->get('/app')
            ->assertOk()
            ->assertSee('hesabını görüntülüyorsunuz')
            ->assertSee('Yönetici Paneline Dön');
    }

    public function test_tenant_show_page_lists_users(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->for($tenant)->create(['name' => 'Listelenen Kullanıcı']);

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class]);

        $this->actingAs($this->admin, 'central_web')
            ->get("/central/tenants/{$tenant->id}")
            ->assertOk()
            ->assertSee('Listelenen Kullanıcı')
            ->assertSee('Bu kullanıcı olarak gir');
    }
}
