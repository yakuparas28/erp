<?php

namespace Tests\Feature\Central\Web;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/central/login')
            ->assertOk()
            ->assertSee('Yönetim Paneline Giriş');
    }

    public function test_super_admin_can_login_with_valid_credentials(): void
    {
        $admin = SuperAdmin::factory()->create(['email' => 'admin@platform.test']);

        $response = $this->post('/central/login', [
            'email' => 'admin@platform.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/central/tenants');
        $this->assertAuthenticatedAs($admin, 'central_web');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        SuperAdmin::factory()->create(['email' => 'admin@platform.test']);

        $response = $this->from('/central/login')->post('/central/login', [
            'email' => 'admin@platform.test',
            'password' => 'yanlis',
        ]);

        $response->assertRedirect('/central/login')->assertSessionHasErrors('email');
        $this->assertGuest('central_web');
    }

    public function test_authenticated_super_admin_can_logout(): void
    {
        $admin = SuperAdmin::factory()->create();

        $this->actingAs($admin, 'central_web')
            ->post('/central/logout')
            ->assertRedirect('/central/login');

        $this->assertGuest('central_web');
    }

    public function test_guests_are_redirected_to_login_from_protected_pages(): void
    {
        $this->get('/central/tenants')->assertRedirect('/central/login');
    }

    public function test_authenticated_super_admin_visiting_login_is_sent_to_the_panel(): void
    {
        $admin = SuperAdmin::factory()->create();

        $this->actingAs($admin, 'central_web')
            ->get('/central/login')
            ->assertRedirect('/central/tenants');
    }

    public function test_home_page_redirects_to_central_login(): void
    {
        $this->get('/')->assertRedirect('/central/login');
    }
}
