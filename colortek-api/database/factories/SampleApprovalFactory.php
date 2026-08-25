<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sample;
use App\Models\SampleApproval;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SampleApproval> */
final class SampleApprovalFactory extends Factory
{
    protected $model = SampleApproval::class;

    public function definition(): array
    {
        return [
            'sample_id' => Sample::factory(),
            'type' => 'client',
            'recorded_by_user_id' => 1,
        ];
    }
}
