<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteMeasurement;
use App\Models\SiteVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteMeasurement> */
final class SiteMeasurementFactory extends Factory
{
    protected $model = SiteMeasurement::class;

    public function definition(): array
    {
        return [
            'site_visit_id' => SiteVisit::factory(),
            'element_name' => fake()->word(),
        ];
    }
}
