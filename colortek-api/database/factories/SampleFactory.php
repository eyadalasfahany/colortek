<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SampleStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sample>
 */
class SampleFactory extends Factory
{
    protected $model = Sample::class;

    public function definition(): array
    {
        $client = Client::factory()->create();
        $project = Project::factory()->create(['client_id' => $client->id]);
        $user = User::factory()->create();

        return [
            'reference' => fake()->unique()->regexify('[A-Z]{2}[0-9]{4}-S[1-9]'),
            'client_id' => $client->id,
            'project_id' => $project->id,
            'root_sample_id' => null,
            'attempt_number' => 1,
            'requested_by_user_id' => $user->id,
            'requested_at' => now(),
            'color' => fake()->safeColorName(),
            'status' => SampleStatus::Draft,
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
