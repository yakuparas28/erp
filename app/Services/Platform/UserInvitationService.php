<?php

namespace App\Services\Platform;

use App\Mail\TemplatedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\NotificationTemplateService;
use App\Services\Mail\TenantMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserInvitationService
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly TenantMailer $mailer,
    ) {}

    /**
     * Tenant'a yeni kullanıcı açar, rollerini atar ve giriş bilgilerini
     * tenant'ın kendi SMTP ayarı + (varsa) kendi şablonuyla gönderir.
     *
     * @param  list<string>  $roles
     */
    public function invite(Tenant $tenant, string $name, string $email, array $roles): User
    {
        [$user, $password] = DB::transaction(function () use ($tenant, $name, $email, $roles): array {
            $password = Str::password(12);

            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->tenant_id = $tenant->id;
            $user->save();

            setPermissionsTeamId($tenant->id);
            $user->syncRoles($roles);

            return [$user, $password];
        });

        $rendered = $this->templates->render('user_invitation', $tenant->id, [
            'kullanici_adi' => $user->name,
            'kullanici_email' => $user->email,
            'firma_adi' => $tenant->name,
            'gecici_sifre' => $password,
            'uygulama_adi' => config('app.name'),
        ]);

        $this->mailer->send($tenant->id, $user->email, new TemplatedMail($rendered['subject'], $rendered['body']));

        return $user;
    }
}
