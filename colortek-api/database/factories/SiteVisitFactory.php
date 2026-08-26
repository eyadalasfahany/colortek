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
            'visited_on' => now()->toDateString(),
            'readiness' => SiteReadiness::Pending,
        ];
    }
}
