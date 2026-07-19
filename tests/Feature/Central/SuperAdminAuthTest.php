<?php

namespace Tests\Feature\Central;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_login_and_receive_a_token(): void
    {
        SuperAdmin::factory()->create(['email' => 'admin@platform.test']);

        $response = $this->postJson('/api/central/login', [
            'email' => 'admin@platform.test',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        SuperAdmin::factory()->create(['email' => 'admin@platform.test']);

        $this->postJson('/api/central/login', [
            'email' => 'admin@platform.test',
            'password' => 'yanlis-sifre',
        ])->assertUnprocessable();
    }

    public function test_me_endpoint_returns_authenticated_super_admin(): void
    {
        $admin = SuperAdmin::factory()->create();
        $token = $admin->createToken('central')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/central/me')
            ->assertOk()
            ->assertJsonPath('email', $admin->email);
    }

    public function test_me_endpoint_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/central/me')->assertUnauthorized();
    }

    public function test_tenant_user_token_cannot_access_central_routes(): void
    {
        $user = User::factory()->for(Tenant::factory())->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/central/me')
            ->assertUnauthorized();
    }
}
