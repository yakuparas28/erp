<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_can_be_created_with_default_accounting_mode(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Acme AŞ']);

        $this->assertDatabaseHas('tenants', ['name' => 'Acme AŞ']);
        $this->assertSame('continental', $tenant->accounting_mode);
    }

    public function test_a_user_belongs_to_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $this->assertTrue($user->tenant->is($tenant));
    }

    public function test_accounting_mode_accepts_anglo_saxon(): void
    {
        $tenant = Tenant::factory()->angloSaxon()->create();

        $this->assertSame('anglo_saxon', $tenant->accounting_mode);
    }
}
