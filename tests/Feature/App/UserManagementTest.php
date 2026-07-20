<?php

namespace Tests\Feature\App;

use App\Mail\TemplatedMail;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, NotificationTemplateSeeder::class]);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('Tenant Admin');
    }

    public function test_users_page_lists_only_own_tenant_users(): void
    {
        User::factory()->for($this->tenant)->create(['name' => 'Bizim Eleman']);
        User::factory()->for(Tenant::factory())->create(['name' => 'Yabancı Eleman']);

        $this->actingAs($this->admin, 'web')
            ->get('/app/users')
            ->assertOk()
            ->assertSee('Bizim Eleman')
            ->assertDontSee('Yabancı Eleman');
    }

    public function test_admin_can_invite_a_user_with_role(): void
    {
        Mail::fake();

        $this->actingAs($this->admin, 'web')->post('/app/users', [
            'name' => 'Yeni Çalışan',
            'email' => 'calisan@firma.test',
            'roles' => ['Warehouse Operator'],
        ])->assertRedirect();

        $user = User::where('email', 'calisan@firma.test')->firstOrFail();
        $this->assertSame($this->tenant->id, $user->tenant_id);

        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($user->hasRole('Warehouse Operator'));

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo('calisan@firma.test'));
    }

    public function test_admin_can_update_user_roles(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $user = User::factory()->for($this->tenant)->create();
        $user->assignRole('Warehouse Operator');

        $this->actingAs($this->admin, 'web')->put("/app/users/{$user->id}", [
            'name' => $user->name,
            'roles' => ['Tenant Admin'],
        ])->assertRedirect();

        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($user->fresh()->hasRole('Tenant Admin'));
        $this->assertFalse($user->fresh()->hasRole('Warehouse Operator'));
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->actingAs($this->admin, 'web')
            ->from('/app/users')
            ->delete("/app/users/{$this->admin->id}")
            ->assertRedirect('/app/users')
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_last_tenant_admin_role_cannot_be_removed(): void
    {
        $this->actingAs($this->admin, 'web')->put("/app/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'roles' => ['Warehouse Operator'],
        ])->assertSessionHasErrors('roles');

        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($this->admin->fresh()->hasRole('Tenant Admin'));
    }

    public function test_foreign_tenant_user_cannot_be_managed(): void
    {
        $foreignUser = User::factory()->for(Tenant::factory())->create();

        $this->actingAs($this->admin, 'web')
            ->delete("/app/users/{$foreignUser->id}")
            ->assertNotFound();
    }

    public function test_operator_cannot_access_user_management(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $operator = User::factory()->for($this->tenant)->create();
        $operator->assignRole('Warehouse Operator');

        $this->actingAs($operator, 'web')->get('/app/users')->assertForbidden();
    }
}
