<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->regexify('SO[0-9]{4}'),
            'client_id' => Client::factory(),
            'total_value' => fake()->randomFloat(2, 10000, 500000),
            'currency' => 'EGP',
            'status' => QuotationStatus::Accepted,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => QuotationStatus::Locked,
            'locked_at' => now(),
        ]);
    }
}
