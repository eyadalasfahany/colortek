<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SampleApprovalDecision;
use App\Enums\SampleApprovalType;
use App\Models\Sample;
use App\Models\SampleApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SampleApproval>
 */
class SampleApprovalFactory extends Factory
{
    protected $model = SampleApproval::class;

    public function definition(): array
    {
        return [
            'sample_id' => Sample::factory(),
            'type' => SampleApprovalType::Manager,
            'decision' => SampleApprovalDecision::Approved,
            'recorded_by_user_id' => User::factory(),
            'decided_at' => now(),
        ];
    }
}
