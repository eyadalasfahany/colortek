<?php
declare(strict_types=1);
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;
beforeEach(fn () => $this->seed(ReferenceSeeder::class));
use App\Models\Project;
use App\Models\User;
it('search project', function (): void {
    $m = User::factory()->create(); $m->assignRole('management');
    Project::factory()->create(['reference' => 'SO9577']);
    Sanctum::actingAs($m);
    expect(collect($this->getJson('/api/v1/search?q=SO9577')->json('data.projects'))->pluck('reference'))->toContain('SO9577');
});
