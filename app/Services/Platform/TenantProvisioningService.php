<?php

namespace App\Services\Platform;

use App\Mail\TenantAdminInvitationMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Tenant'ı firma bilgileriyle oluşturur, Tenant Admin kullanıcısını açar
     * ve giriş bilgilerini e-posta ile gönderir.
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

            return [$tenant, $adminUser, $password];
        });

        Mail::to($adminUser->email)->send(new TenantAdminInvitationMail($tenant, $adminUser, $password));

        return $tenant;
    }
}
