<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
final class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('EMP-####'),
            'name' => fake()->name(),
            'active' => true,
        ];
    }

    public function inDepartment(string $code): static
    {
        return $this->state(function () use ($code): array {
            $department = Department::query()->where('code', $code)->first()
                ?? Department::factory()->create(['code' => $code]);

            return ['department_id' => $department->id];
        });
    }
}
