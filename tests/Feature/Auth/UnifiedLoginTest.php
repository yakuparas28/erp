<?php

namespace Tests\Feature\Auth;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee("ERP'ye Giriş", false);
    }

    public function test_super_admin_logs_in_and_lands_on_central_panel(): void
    {
        $admin = SuperAdmin::factory()->create(['email' => 'admin@platform.test']);

        $this->post('/login', ['email' => 'admin@platform.test', 'password' => 'password'])
            ->assertRedirect('/central/tenants');

        $this->assertAuthenticatedAs($admin, 'central_web');
    }

    public function test_tenant_user_logs_in_and_lands_on_app_dashboard(): void
    {
        $user = User::factory()->for(Tenant::factory())->create(['email' => 'kullanici@firma.test']);

        $this->post('/login', ['email' => 'kullanici@firma.test', 'password' => 'password'])
            ->assertRedirect('/app');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->for(Tenant::factory())->create(['email' => 'kullanici@firma.test']);

        $this->from('/login')
            ->post('/login', ['email' => 'kullanici@firma.test', 'password' => 'yanlis'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
        $this->assertGuest('central_web');
    }

    public function test_logout_works_for_both_guards(): void
    {
        $admin = SuperAdmin::factory()->create();
        $this->actingAs($admin, 'central_web')->post('/logout')->assertRedirect('/login');
        $this->assertGuest('central_web');

        $user = User::factory()->for(Tenant::factory())->create();
        $this->actingAs($user, 'web')->post('/logout')->assertRedirect('/login');
        $this->assertGuest('web');
    }

    public function test_guests_are_redirected_to_login_from_protected_pages(): void
    {
        $this->get('/central/tenants')->assertRedirect('/login');
        $this->get('/app')->assertRedirect('/login');
    }

    public function test_authenticated_users_visiting_login_go_to_their_panel(): void
    {
        $admin = SuperAdmin::factory()->create();
        $this->actingAs($admin, 'central_web')->get('/login')->assertRedirect('/central/tenants');

        auth('central_web')->logout();

        $user = User::factory()->for(Tenant::factory())->create();
        $this->actingAs($user, 'web')->get('/login')->assertRedirect('/app');
    }

    public function test_old_central_login_url_redirects_to_unified_login(): void
    {
        $this->get('/central/login')->assertRedirect('/login');
    }

    public function test_home_page_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
