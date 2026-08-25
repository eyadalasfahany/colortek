<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Formula;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Formula> */
final class FormulaFactory extends Factory
{
    protected $model = Formula::class;

    public function definition(): array
    {
        return [
            'sample_id' => Sample::factory(),
            'reference' => 'F-'.fake()->unique()->numerify('####'),
            'body' => fake()->sentence(),
            'status' => 'draft',
        ];
    }
}
