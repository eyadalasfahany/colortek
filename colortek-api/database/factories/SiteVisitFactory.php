<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SiteReadiness;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteVisit>
 */
class SiteVisitFactory extends Factory
{
    protected $model = SiteVisit::class;

    public function definition(): array
    {
        $project = Project::factory()->create();

        return [
            'reference' => fake()->unique()->regexify('[A-Z]{2}[0-9]{4}-SV[1-9]'),
            'project_id' => $project->id,
            'visit_number' => 1,
            'engineer_user_id' => User::factory(),
            // NOT NULL on the table: the form snapshots the project name and
            // address as they read on the day of the visit.
            'project_name_on_form' => $project->name,
            'address_on_form' => fake()->streetAddress().', '.fake()->city(),
            'visited_on' => now()->toDateString(),
            'readiness' => SiteReadiness::Pending,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'readiness' => SiteReadiness::Ready,
            'submitted_at' => now(),
        ]);
    }

    public function notReady(): static
    {
        return $this->state(fn (): array => [
            'readiness' => SiteReadiness::NotReady,
            'submitted_at' => now(),
        ]);
    }
}
