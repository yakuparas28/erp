<?php

namespace Database\Factories;

use App\Models\MailSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailSetting>
 */
class MailSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'host' => 'smtp.'.fake()->domainName(),
            'port' => 587,
            'username' => fake()->userName(),
            'password' => 'gizli-sifre',
            'encryption' => 'tls',
            'from_address' => fake()->companyEmail(),
            'from_name' => fake()->company(),
            'is_active' => true,
        ];
    }
}
