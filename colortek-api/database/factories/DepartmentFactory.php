<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('dept_????'),
            'name' => ['en' => fake()->word(), 'ar' => fake()->word()],
            'is_queue' => true,
            'active' => true,
        ];
    }
}
