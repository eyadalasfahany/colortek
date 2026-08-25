<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('EMP-####'),
            'name' => fake()->name(),
            'department_id' => Department::query()->where('code', 'tinting')->value('id'),
            'user_id' => null,
            'active' => true,
        ];
    }

    public function inDepartment(string $code): static
    {
        return $this->state(function () use ($code): array {
            $department = Department::query()->where('code', $code)->firstOrFail();

            return ['department_id' => $department->id];
        });
    }
}
