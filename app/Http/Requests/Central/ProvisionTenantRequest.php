<?php

namespace App\Http\Requests\Central;

/**
 * Panelden tenant oluşturma: firma bilgileri + zorunlu Tenant Admin hesabı.
 */
class ProvisionTenantRequest extends StoreTenantRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'firma adı',
            'admin_name' => 'yönetici adı',
            'admin_email' => 'yönetici e-postası',
        ];
    }
}
