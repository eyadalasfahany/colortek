<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BlockerCategory;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockerCategory>
 */
class BlockerCategoryFactory extends Factory
{
    protected $model = BlockerCategory::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('blocker_????'),
            'name' => ['en' => fake()->words(2, true), 'ar' => fake()->word()],
            'requires_expected_date' => false,
            'active' => true,
        ];
    }

    public function requiresExpectedDate(): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires_expected_date' => true,
        ]);
    }

    public function notifies(Department $department): static
    {
        return $this->state(fn (array $attributes): array => [
            'notifies_department_id' => $department->id,
        ]);
    }
}
