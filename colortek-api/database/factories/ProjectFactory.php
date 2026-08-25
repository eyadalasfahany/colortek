<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'reference' => fake()->unique()->regexify('PRJ-[0-9]{5}'),
            'name' => fake()->company().' Project',
            'stage' => 'lead',
            'status' => 'active',
            'site_ready' => true,
            'block_all_when_site_not_ready' => false,
        ];
    }

    public function siteNotReady(): static
    {
        return $this->state(fn (array $attributes): array => [
            'site_ready' => false,
        ]);
    }
}
