@php($fieldClass = 'w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0')
<div class="grid grid-cols-12 gap-3">
    <div class="col-span-12 sm:col-span-8">
        <label class="text-sm font-semibold text-gray-900 mb-1 block">SMTP Sunucusu <span class="text-danger">*</span></label>
        <input type="text" name="host" required value="{{ old('host', $setting?->host) }}" placeholder="smtp.ornek.com" class="{{ $fieldClass }}">
    </div>
    <div class="col-span-6 sm:col-span-2">
        <label class="text-sm font-semibold text-gray-900 mb-1 block">Port <span class="text-danger">*</span></label>
        <input type="number" name="port" required value="{{ old('port', $setting?->port ?? 587) }}" class="{{ $fieldClass }}">
    </div>
    <div class="col-span-6 sm:col-span-2">
        <label class="text-sm font-semibold text-gray-900 mb-1 block">Şifreleme</label>
        <select name="encryption" class="{{ $fieldClass }}">
            @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Yok'] as $value => $label)
                <option value="{{ $value }}" @selected(old('encryption', $setting?->encryption ?? 'tls') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-span-12 sm:col-span-6">
        <label class="text-sm font-semibold text-gray-900 mb-1 block">Kullanıcı Adı</label>
        <input type="text" name="username" value="{{ old('username', $setting?->username) }}" class="{{ $fieldClass }}">
    </div>
    <div class="col-span-12 sm:col-span-6">
        <label class="text-sm font-semibold text-gray-900 mb-1 block">Şifre</label>
        <input type="password" name="password" autocomplete="new-password" placeholder="{{ $setting ? 'Değiştirmek için doldurun' : '' }}" class="{{ $fieldClass }}">
    </div>
    <div class="col-span-12 sm:col-span-6">
        <label class="text-sm font-semibold text-gray-900 mb-1 block">Gönderen Adresi <span class="text-danger">*</span></label>
        <input type="email" name="from_address" required value="{{ old('from_address', $setting?->from_address) }}" class="{{ $fieldClass }}">
    </div>
    <div class="col-span-12 sm:col-span-6">
        <label class="text-sm font-semibold text-gray-900 mb-1 block">Gönderen Adı <span class="text-danger">*</span></label>
        <input type="text" name="from_name" required value="{{ old('from_name', $setting?->from_name) }}" class="{{ $fieldClass }}">
    </div>
    <div class="col-span-12 flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" id="mail_is_active_{{ $formId ?? 'main' }}" name="is_active" value="1" class="size-4 rounded border-border-color" @checked(old('is_active', $setting?->is_active ?? true))>
        <label for="mail_is_active_{{ $formId ?? 'main' }}" class="text-sm text-default">Aktif (kapalıysa üst seviye/varsayılan ayar kullanılır)</label>
    </div>
</div>
