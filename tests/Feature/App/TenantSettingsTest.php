<?php

namespace Tests\Feature\App;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $tenantAdmin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, NotificationTemplateSeeder::class]);

        $this->tenant = Tenant::factory()->create();

        $this->tenantAdmin = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $this->tenantAdmin->assignRole('Tenant Admin');

        $this->operator = User::factory()->for($this->tenant)->create();
        $this->operator->assignRole('Warehouse Operator');
    }

    public function test_tenant_admin_can_view_and_save_own_mail_settings(): void
    {
        $this->actingAs($this->tenantAdmin, 'web')
            ->get('/app/settings/mail')
            ->assertOk()
            ->assertSee('E-posta Ayarları');

        $this->actingAs($this->tenantAdmin, 'web')->put('/app/settings/mail', [
            'host' => 'smtp.kendifirmam.test',
            'port' => 587,
            'encryption' => 'tls',
            'from_address' => 'bildirim@kendifirmam.test',
            'from_name' => 'Kendi Firmam',
        ])->assertRedirect('/app/settings/mail');

        $this->assertDatabaseHas('mail_settings', [
            'tenant_id' => $this->tenant->id,
            'host' => 'smtp.kendifirmam.test',
        ]);
    }

    public function test_warehouse_operator_cannot_access_settings(): void
    {
        $this->actingAs($this->operator, 'web')->get('/app/settings/mail')->assertForbidden();
        $this->actingAs($this->operator, 'web')->get('/app/settings/notification-templates')->assertForbidden();
    }

    public function test_tenant_admin_sees_platform_default_templates(): void
    {
        $this->actingAs($this->tenantAdmin, 'web')
            ->get('/app/settings/notification-templates')
            ->assertOk()
            ->assertSee('Tenant Yöneticisi Davet E-postası')
            ->assertSee('Platform varsayılanı');
    }

    public function test_saving_a_template_creates_tenant_override(): void
    {
        $this->actingAs($this->tenantAdmin, 'web')
            ->put('/app/settings/notification-templates/tenant_admin_invitation', [
                'subject' => 'Bizim Firmaya Özel Konu',
                'body' => 'Özel gövde {{yonetici_adi}}',
            ])->assertRedirect();

        $this->assertDatabaseHas('notification_templates', [
            'tenant_id' => $this->tenant->id,
            'key' => 'tenant_admin_invitation',
            'subject' => 'Bizim Firmaya Özel Konu',
        ]);

        // Platform varsayılanı değişmedi
        $this->assertDatabaseHas('notification_templates', [
            'tenant_id' => null,
            'key' => 'tenant_admin_invitation',
        ]);
    }

    public function test_resetting_a_template_deletes_the_override(): void
    {
        NotificationTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'tenant_admin_invitation',
        ]);

        $this->actingAs($this->tenantAdmin, 'web')
            ->delete('/app/settings/notification-templates/tenant_admin_invitation')
            ->assertRedirect();

        $this->assertDatabaseMissing('notification_templates', [
            'tenant_id' => $this->tenant->id,
            'key' => 'tenant_admin_invitation',
        ]);
    }
}
