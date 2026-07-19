<?php

namespace Tests\Feature\Central\Web;

use App\Models\MailSetting;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Services\Mail\TenantMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::factory()->create();
    }

    public function test_platform_mail_settings_page_renders(): void
    {
        $this->actingAs($this->admin, 'central_web')
            ->get('/central/settings/mail')
            ->assertOk()
            ->assertSee('Platform E-posta Ayarları');
    }

    public function test_platform_mail_settings_can_be_saved_and_password_is_encrypted(): void
    {
        $this->actingAs($this->admin, 'central_web')->put('/central/settings/mail', [
            'host' => 'smtp.platform.test',
            'port' => 587,
            'username' => 'bildirim@platform.test',
            'password' => 'cok-gizli',
            'encryption' => 'tls',
            'from_address' => 'bildirim@platform.test',
            'from_name' => 'ERP Platform',
            'is_active' => 1,
        ])->assertRedirect('/central/settings/mail');

        $setting = MailSetting::whereNull('tenant_id')->firstOrFail();
        $this->assertSame('cok-gizli', $setting->password);

        $rawPassword = DB::table('mail_settings')->where('id', $setting->id)->value('password');
        $this->assertNotSame('cok-gizli', $rawPassword);
    }

    public function test_updating_without_password_keeps_existing_one(): void
    {
        MailSetting::factory()->create(['password' => 'ilk-sifre', 'host' => 'eski.host']);

        $this->actingAs($this->admin, 'central_web')->put('/central/settings/mail', [
            'host' => 'yeni.host',
            'port' => 465,
            'encryption' => 'ssl',
            'from_address' => 'a@b.test',
            'from_name' => 'Test',
        ])->assertRedirect();

        $setting = MailSetting::whereNull('tenant_id')->firstOrFail();
        $this->assertSame('yeni-degil-ilk-sifre' === 'x' ? '' : 'ilk-sifre', $setting->password);
        $this->assertSame('yeni.host', $setting->host);
    }

    public function test_tenant_mail_settings_can_be_saved_from_tenant_page(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin, 'central_web')->put("/central/tenants/{$tenant->id}/mail-settings", [
            'host' => 'smtp.tenant.test',
            'port' => 587,
            'encryption' => 'tls',
            'from_address' => 'info@tenant.test',
            'from_name' => 'Tenant AŞ',
        ])->assertRedirect("/central/tenants/{$tenant->id}");

        $this->assertDatabaseHas('mail_settings', ['tenant_id' => $tenant->id, 'host' => 'smtp.tenant.test']);
    }

    public function test_mailer_resolution_prefers_tenant_then_platform(): void
    {
        $tenant = Tenant::factory()->create();
        $mailer = app(TenantMailer::class);

        $this->assertNull($mailer->resolveSettings($tenant->id));

        $platform = MailSetting::factory()->create();
        $this->assertTrue($mailer->resolveSettings($tenant->id)->is($platform));

        $own = MailSetting::factory()->create(['tenant_id' => $tenant->id]);
        $this->assertTrue($mailer->resolveSettings($tenant->id)->is($own));

        $own->update(['is_active' => false]);
        $this->assertTrue($mailer->resolveSettings($tenant->id)->is($platform));
    }
}
