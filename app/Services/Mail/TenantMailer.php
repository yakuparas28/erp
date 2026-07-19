<?php

namespace App\Services\Mail;

use App\Models\MailSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class TenantMailer
{
    /**
     * Postayı ilgili SMTP ayarıyla gönderir: tenant'ın kendi ayarı >
     * platform ayarı > .env varsayılanı. Ayarlar DB'den okunur ve
     * on-demand mailer olarak kurulur.
     */
    public function send(?int $tenantId, string $to, Mailable $mailable): void
    {
        $settings = $this->resolveSettings($tenantId);

        if ($settings === null) {
            Mail::to($to)->send($mailable);

            return;
        }

        config(['mail.mailers.tenant_dynamic' => $settings->toMailerConfig()]);
        app('mail.manager')->purge('tenant_dynamic');

        Mail::mailer('tenant_dynamic')
            ->to($to)
            ->send($mailable->from($settings->from_address, $settings->from_name));
    }

    public function resolveSettings(?int $tenantId): ?MailSetting
    {
        if ($tenantId !== null) {
            $tenantSettings = MailSetting::where('tenant_id', $tenantId)->where('is_active', true)->first();

            if ($tenantSettings !== null) {
                return $tenantSettings;
            }
        }

        return MailSetting::whereNull('tenant_id')->where('is_active', true)->first();
    }
}
