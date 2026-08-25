<?php

declare(strict_types=1);
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceSeeder::class));
use App\Models\User;

it('returns sse', function () {
    $u = User::factory()->create();
    $u->assignRole('management');
    Sanctum::actingAs($u);
    $this->get('/api/v1/stream', ['Accept' => 'text/event-stream'])->assertOk();
});
