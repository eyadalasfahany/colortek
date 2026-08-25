<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

it('returns the permission list, not role names, from me', function (): void {
    $this->seed(ReferenceSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('sales');

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.permissions.0', fn (string $p): bool => str_contains($p, '.'))
        ->assertJsonMissingPath('data.roles.0');
});
