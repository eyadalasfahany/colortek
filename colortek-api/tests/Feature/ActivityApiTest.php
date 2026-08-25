<?php

declare(strict_types=1);
use App\Enums\ActivitySeverity;
use App\Models\Project;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceSeeder::class));
it('returns visible activity only', function (): void {
    $visible = Project::factory()->create();
    $hidden = Project::factory()->create();
    $sales = User::factory()->create();
    $sales->assignRole('sales');
    $visible->update(['sales_user_id' => $sales->id]);
    $r = app(ActivityRecorder::class);
    $r->record('task.created', ActivitySeverity::Info, 'Visible', 'v', project: $visible);
    $r->record('task.created', ActivitySeverity::Info, 'Hidden', 'h', project: $hidden);
    Sanctum::actingAs($sales);
    expect(collect($this->getJson('/api/v1/activity')->json('data'))->pluck('message'))->toContain('Visible')->not->toContain('Hidden');
});
