<?php

namespace Tests\Feature\Central\Web;

use App\Mail\TemplatedMail;
use App\Models\NotificationTemplate;
use App\Models\SuperAdmin;
use App\Services\Mail\NotificationTemplateService;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class, RoleSeeder::class, NotificationTemplateSeeder::class]);
        $this->admin = SuperAdmin::factory()->create();
    }

    public function test_templates_page_lists_seeded_templates(): void
    {
        $this->actingAs($this->admin, 'central_web')
            ->get('/central/settings/notification-templates')
            ->assertOk()
            ->assertSee('Tenant Yöneticisi Davet E-postası');
    }

    public function test_template_can_be_edited_from_the_panel(): void
    {
        $template = NotificationTemplate::where('key', 'tenant_admin_invitation')->firstOrFail();

        $this->actingAs($this->admin, 'central_web')
            ->put("/central/settings/notification-templates/{$template->id}", [
                'subject' => 'Yeni Konu: {{firma_adi}}',
                'body' => 'Merhaba {{yonetici_adi}}, şifreniz: {{gecici_sifre}}',
            ])->assertRedirect();

        $this->assertSame('Yeni Konu: {{firma_adi}}', $template->fresh()->subject);
    }

    public function test_render_fills_placeholders(): void
    {
        $rendered = app(NotificationTemplateService::class)->render('tenant_admin_invitation', null, [
            'yonetici_adi' => 'Ayşe',
            'yonetici_email' => 'ayse@x.test',
            'firma_adi' => 'X AŞ',
            'gecici_sifre' => 'abc123',
            'uygulama_adi' => 'ERP',
        ]);

        $this->assertStringContainsString('X AŞ', $rendered['subject']);
        $this->assertStringContainsString('Ayşe', $rendered['body']);
        $this->assertStringNotContainsString('{{', $rendered['body']);
    }

    public function test_tenant_specific_template_overrides_platform_default(): void
    {
        NotificationTemplate::factory()->create([
            'tenant_id' => 42,
            'key' => 'tenant_admin_invitation',
            'subject' => 'Tenant Özel Konu',
            'body' => 'Özel gövde',
        ]);

        $service = app(NotificationTemplateService::class);

        $this->assertSame('Tenant Özel Konu', $service->resolve('tenant_admin_invitation', 42)->subject);
        $this->assertNotSame('Tenant Özel Konu', $service->resolve('tenant_admin_invitation', null)->subject);
    }

    public function test_edited_template_is_used_when_provisioning_a_tenant(): void
    {
        Mail::fake();

        NotificationTemplate::where('key', 'tenant_admin_invitation')->firstOrFail()
            ->update(['subject' => 'ÖZEL: {{firma_adi}} hesabınız']);

        $this->actingAs($this->admin, 'central_web')->post('/central/tenants', [
            'name' => 'Şablonlu AŞ',
            'accounting_mode' => 'continental',
            'admin_name' => 'Veli',
            'admin_email' => 'veli@sablon.test',
        ])->assertRedirect();

        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->subjectLine === 'ÖZEL: Şablonlu AŞ hesabınız');
    }
}
