<?php

namespace Modules\Fleet\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fleet\Models\Project;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'ad' => fake()->company().' Projesi',
            'aktif' => true,
        ];
    }
}
