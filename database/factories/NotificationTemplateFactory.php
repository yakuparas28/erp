<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ];
    }
}
