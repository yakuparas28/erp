<?php

namespace Tests\Feature\Central\Web;

use App\Mail\TemplatedMail;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LicensePackageSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ModuleSeeder::class, LicensePackageSeeder::class, RoleSeeder::class, NotificationTemplateSeeder::class]);
        $this->admin = SuperAdmin::factory()->create();
    }

    public function test_creating_a_tenant_provisions_its_admin_user_and_sends_invitation(): void
    {
        Mail::fake();

        $this->actingAs($this->admin, 'central_web')->post('/central/tenants', [
            'name' => 'Provizyon AŞ',
            'accounting_mode' => 'continental',
            'tax_number' => '1234567890',
            'tax_office' => 'Kadıköy',
            'email' => 'info@provizyon.test',
            'phone' => '0216 000 00 00',
            'address' => 'İstanbul',
            'admin_name' => 'Ali Yönetici',
            'admin_email' => 'ali@provizyon.test',
        ])->assertRedirect();

        $tenant = Tenant::where('name', 'Provizyon AŞ')->firstOrFail();
        $this->assertSame('1234567890', $tenant->tax_number);

        $adminUser = User::where('email', 'ali@provizyon.test')->firstOrFail();
        $this->assertSame($tenant->id, $adminUser->tenant_id);

        setPermissionsTeamId($tenant->id);
        $this->assertTrue($adminUser->hasRole('Tenant Admin'));

        Mail::assertSent(TemplatedMail::class, function (TemplatedMail $mail) {
            return $mail->hasTo('ali@provizyon.test')
                && str_contains($mail->subjectLine, 'Provizyon AŞ')
                && str_contains($mail->bodyMarkdown, 'Ali Yönetici');
        });
    }

    public function test_admin_email_must_be_unique_across_users(): void
    {
        User::factory()->for(Tenant::factory())->create(['email' => 'mevcut@ornek.test']);

        $this->actingAs($this->admin, 'central_web')->from('/central/tenants')->post('/central/tenants', [
            'name' => 'Çakışan AŞ',
            'accounting_mode' => 'continental',
            'admin_name' => 'Biri',
            'admin_email' => 'mevcut@ornek.test',
        ])->assertSessionHasErrors('admin_email');

        $this->assertDatabaseMissing('tenants', ['name' => 'Çakışan AŞ']);
    }
}
