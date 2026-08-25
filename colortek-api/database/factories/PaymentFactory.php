<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $project = Project::factory()->create();

        return [
            'project_id' => $project->id,
            'quotation_id' => $project->quotation_id,
            'installment_number' => 1,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'currency' => 'EGP',
            'method' => PaymentMethod::BankTransfer,
            'paid_at' => now()->toDateString(),
            'status' => PaymentStatus::PendingConfirmation,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
