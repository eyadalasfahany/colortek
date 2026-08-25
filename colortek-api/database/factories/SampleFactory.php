<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sample> */
final class SampleFactory extends Factory
{
    protected $model = Sample::class;

    public function definition(): array
    {
        return [
            'reference' => 'SMP-'.fake()->unique()->numerify('####'),
            'client_id' => Client::factory(),
            'attempt_number' => 1,
            'requested_at' => now(),
            'color' => fake()->colorName(),
            'status' => 'draft',
            'is_presale' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Sample $sample): void {
            if ($sample->root_sample_id === null) {
                $sample->update(['root_sample_id' => $sample->id]);
            }
        });
    }
}
