<?php

namespace App\Services\Mail;

use App\Models\NotificationTemplate;

class NotificationTemplateService
{
    /**
     * Şablonu çözer (tenant'a özel > platform varsayılanı) ve yer
     * tutucuları doldurur. Güvenlik gereği Blade derlenmez; yalnızca
     * {{degisken}} metin değişimi yapılır.
     *
     * @param  array<string, string>  $data
     * @return array{subject: string, body: string}
     */
    public function render(string $key, ?int $tenantId, array $data): array
    {
        $template = $this->resolve($key, $tenantId);

        return [
            'subject' => $this->fill($template->subject, $data),
            'body' => $this->fill($template->body, $data),
        ];
    }

    public function resolve(string $key, ?int $tenantId): NotificationTemplate
    {
        if ($tenantId !== null) {
            $tenantTemplate = NotificationTemplate::where('key', $key)->where('tenant_id', $tenantId)->first();

            if ($tenantTemplate !== null) {
                return $tenantTemplate;
            }
        }

        return NotificationTemplate::where('key', $key)->whereNull('tenant_id')->firstOrFail();
    }

    /**
     * @param  array<string, string>  $data
     */
    private function fill(string $text, array $data): string
    {
        foreach ($data as $placeholder => $value) {
            $text = str_replace('{{'.$placeholder.'}}', $value, $text);
        }

        return $text;
    }
}
