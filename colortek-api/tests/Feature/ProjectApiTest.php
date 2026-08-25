<?php

declare(strict_types=1);
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceSeeder::class));
use App\Models\Project;
use App\Models\User;

it('lists projects', function () {
    $s = User::factory()->inDepartment('sales')->create();
    $p = Project::factory()->create(['sales_user_id' => $s->id]);
    Sanctum::actingAs($s);
    $this->getJson('/api/v1/projects')->assertOk()->assertJsonFragment(['reference' => $p->reference]);
});
it('workflow delivery stub', function () {
    $m = User::factory()->create();
    $m->assignRole('management');
    $p = Project::factory()->create(['stage' => 'site']);
    Sanctum::actingAs($m);
    $d = collect($this->getJson("/api/v1/projects/{$p->id}/workflow")->json('data.stages'))->firstWhere('key', 'delivery');
    expect($d['configured'])->toBeFalse();
});
