<?php

declare(strict_types=1);
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceSeeder::class));
use App\Models\Project;
use App\Models\User;

it('search', function () {
    $m = User::factory()->create();
    $m->assignRole('management');
    Project::factory()->create(['reference' => 'SO9577']);
    Sanctum::actingAs($m);
    $response = $this->getJson('/api/v1/search?q=SO9577');
    expect(collect($response->json('data.projects'))->pluck('reference'))->toContain('SO9577');
});
