<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeductionSign;
use App\Models\SiteMeasurement;
use App\Models\SiteMeasurementDeduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteMeasurementDeduction>
 */
class SiteMeasurementDeductionFactory extends Factory
{
    protected $model = SiteMeasurementDeduction::class;

    public function definition(): array
    {
        return [
            'measurement_id' => SiteMeasurement::factory(),
            'kind' => 'opening',
            'label' => fake()->word(),
            'count' => 1,
            'length_m' => 1.0,
            'width_m' => 1.0,
            'sign' => DeductionSign::Subtract,
            'sort_order' => 0,
        ];
    }
}
