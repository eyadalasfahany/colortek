<?php
declare(strict_types=1);
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;
beforeEach(fn () => $this->seed(ReferenceSeeder::class));
use App\Models\User;
it('control room', function (): void {
    $m = User::factory()->create(); $m->assignRole('management'); Sanctum::actingAs($m);
    $this->getJson('/api/v1/dashboard/control-room')->assertOk()->assertJsonStructure(['data' => ['kpis', 'active_projects', 'needs_attention']]);
});
