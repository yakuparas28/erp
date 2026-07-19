<x-mail::message>
# Hoş Geldiniz, {{ $adminUser->name }}

**{{ $tenant->name }}** firması için ERP yönetici hesabınız oluşturuldu.

**Giriş bilgileriniz:**

- E-posta: {{ $adminUser->email }}
- Geçici şifre: `{{ $temporaryPassword }}`

İlk girişinizden sonra şifrenizi değiştirmenizi öneririz.

Teşekkürler,<br>
{{ config('app.name') }}
</x-mail::message>
