<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\SiteVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteVisit> */
final class SiteVisitFactory extends Factory
{
    protected $model = SiteVisit::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'visit_number' => 1,
            'status' => 'draft',
        ];
    }
}
