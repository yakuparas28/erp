<?php

namespace App\Services\Platform;

use App\Mail\TemplatedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\NotificationTemplateService;
use App\Services\Mail\TenantMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Services\AccountingDefaultsService;
use Modules\Fleet\Services\FleetDefaultsService;
use Modules\Hr\Services\HrDefaultsService;
use Modules\Inventory\Services\InventoryDefaultsService;

class TenantProvisioningService
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly TenantMailer $mailer,
        private readonly InventoryDefaultsService $inventoryDefaults,
        private readonly AccountingDefaultsService $accountingDefaults,
        private readonly HrDefaultsService $hrDefaults,
        private readonly FleetDefaultsService $fleetDefaults,
    ) {}

    /**
     * Tenant'ı firma bilgileriyle oluşturur, Tenant Admin kullanıcısını açar
     * ve giriş bilgilerini (platform SMTP ayarı + düzenlenebilir şablonla)
     * e-posta ile gönderir.
     *
     * @param  array<string, mixed>  $companyData
     */
    public function createWithAdmin(array $companyData, string $adminName, string $adminEmail): Tenant
    {
        [$tenant, $adminUser, $password] = DB::transaction(function () use ($companyData, $adminName, $adminEmail): array {
            $tenant = Tenant::create($companyData);

            $password = Str::password(12);

            $adminUser = new User([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $password,
            ]);
            $adminUser->tenant_id = $tenant->id;
            $adminUser->save();

            setPermissionsTeamId($tenant->id);
            $adminUser->assignRole('Tenant Admin');

            $this->inventoryDefaults->provision($tenant);
            $this->accountingDefaults->provision($tenant);
            $this->hrDefaults->provision($tenant);
            $this->fleetDefaults->provision($tenant);

            return [$tenant, $adminUser, $password];
        });

        $rendered = $this->templates->render('tenant_admin_invitation', null, [
            'yonetici_adi' => $adminUser->name,
            'yonetici_email' => $adminUser->email,
            'firma_adi' => $tenant->name,
            'gecici_sifre' => $password,
            'uygulama_adi' => config('app.name'),
        ]);

        $this->mailer->send(null, $adminUser->email, new TemplatedMail($rendered['subject'], $rendered['body']));

        return $tenant;
    }
}
