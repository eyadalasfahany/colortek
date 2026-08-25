<?php

declare(strict_types=1);
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceSeeder::class));
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskQueuedNotification;

it('read all', function () {
    $u = User::factory()->create();
    $u->assignRole('management');
    $u->notify(new TaskQueuedNotification(Task::factory()->ready()->create()));
    Sanctum::actingAs($u);
    $this->postJson('/api/v1/notifications/read-all')->assertOk();
    expect($u->fresh()->unreadNotifications()->count())->toBe(0);
});
