<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('EMP###')),
            'name' => fake()->name(),
            'department_id' => Department::factory(),
            'active' => true,
        ];
    }

    public function inDepartment(string $code): static
    {
        return $this->state(fn (): array => [
            'department_id' => Department::query()->where('code', $code)->value('id')
                ?? Department::factory()->create(['code' => $code])->id,
        ]);
    }
}
