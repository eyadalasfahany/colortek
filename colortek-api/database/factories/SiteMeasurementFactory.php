<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteMeasurement;
use App\Models\SiteVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteMeasurement>
 */
class SiteMeasurementFactory extends Factory
{
    protected $model = SiteMeasurement::class;

    public function definition(): array
    {
        return [
            'site_visit_id' => SiteVisit::factory(),
            'page_number' => 1,
            'line_number' => 1,
            'element_name' => fake()->word(),
            'sort_order' => 0,
            'verified' => false,
        ];
    }
}
