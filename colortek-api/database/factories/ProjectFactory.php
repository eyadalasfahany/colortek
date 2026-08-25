<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $client = Client::factory()->create();
        $quotation = Quotation::factory()->for($client)->create();

        return [
            'reference' => $quotation->number,
            'name' => fake()->company().' Project',
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'stage' => 'lead',
            'status' => 'active',
            'sales_user_id' => User::factory(),
            'site_ready' => true,
            'block_all_when_site_not_ready' => false,
        ];
    }

    public function siteNotReady(): static
    {
        return $this->state(fn (array $attributes): array => [
            'site_ready' => false,
        ]);
    }

    public function withQuotation(): static
    {
        return $this->state(function (array $attributes): array {
            $clientId = $attributes['client_id'] ?? Client::factory()->create()->id;
            $quotation = Quotation::factory()->create(['client_id' => $clientId]);

            return [
                'client_id' => $clientId,
                'quotation_id' => $quotation->id,
                'reference' => $quotation->number,
            ];
        });
    }
}
