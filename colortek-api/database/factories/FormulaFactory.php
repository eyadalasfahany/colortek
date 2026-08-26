<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FormulaStatus;
use App\Models\Employee;
use App\Models\Formula;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Formula>
 */
class FormulaFactory extends Factory
{
    protected $model = Formula::class;

    public function definition(): array
    {
        $sample = Sample::factory()->create();
        $employee = Employee::factory()->inDepartment('tinting')->create();

        return [
            'reference' => $sample->reference.'-F1',
            'sample_id' => $sample->id,
            'version' => 1,
            'body' => fake()->paragraph(),
            'author_employee_id' => $employee->id,
            'author_user_id' => User::factory(),
            'authored_at' => now()->toDateString(),
            'status' => FormulaStatus::Draft,
        ];
    }

    public function withSheetOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'body' => null,
        ]);
    }
}
