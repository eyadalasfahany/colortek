<?php

declare(strict_types=1);

use App\Enums\ActivitySeverity;
use App\Models\Project;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceSeeder::class));

it('returns only visible activity', function (): void {
    $visible = Project::factory()->create();
    $hidden = Project::factory()->create();
    $sales = User::factory()->create();
    $sales->assignRole('sales');
    $visible->update(['sales_user_id' => $sales->id]);
    $r = app(ActivityRecorder::class);
    $r->record('task.created', ActivitySeverity::Info, 'Visible', 'v', project: $visible);
    $r->record('task.created', ActivitySeverity::Info, 'Hidden', 'h', project: $hidden);
    Sanctum::actingAs($sales);
    $res = $this->getJson('/api/v1/activity')->assertOk();
    expect(collect($res->json('data'))->pluck('message'))->toContain('Visible')->not->toContain('Hidden');
});

it('filters by since', function (): void {
    $m = User::factory()->create();
    $m->assignRole('management');
    $p = Project::factory()->create();
    $r = app(ActivityRecorder::class);
    $first = $r->record('task.created', ActivitySeverity::Info, 'First', 'f', project: $p);
    $r->record('task.created', ActivitySeverity::Info, 'Second', 's', project: $p);
    Sanctum::actingAs($m);
    $this->getJson('/api/v1/activity?since='.$first->id)->assertOk()->assertJsonFragment(['message' => 'Second']);
});
