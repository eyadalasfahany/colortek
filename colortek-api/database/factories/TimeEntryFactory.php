<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TimeEntry> */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        $startedAt = now()->subHours(2);

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => now(),
            'seconds' => 7200,
            'source' => 'timer',
            'needs_review' => false,
        ];
    }
}
