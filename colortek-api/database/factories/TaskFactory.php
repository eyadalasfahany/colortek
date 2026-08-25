<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'reference' => fake()->unique()->regexify('[A-Z]{2}[0-9]{4}-T[0-9]{3}'),
            'title' => fake()->sentence(3),
            'instructions' => fake()->optional()->paragraph(),
            'department_id' => Department::factory(),
            'status' => TaskStatus::Ready,
            'priority' => TaskPriority::Normal,
            'ready_at' => now(),
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskStatus::Ready,
            'claimed_by_user_id' => null,
            'claimed_at' => null,
            'ready_at' => now(),
        ]);
    }

    public function claimed(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user): array {
            $claimant = $user ?? User::factory()->create();

            return [
                'status' => TaskStatus::Claimed,
                'claimed_by_user_id' => $claimant->id,
                'claimed_at' => now(),
            ];
        });
    }

    public function inProgress(?User $user = null): static
    {
        return $this->claimed($user)->state(fn (array $attributes): array => [
            'status' => TaskStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function blocked(?User $user = null): static
    {
        return $this->inProgress($user)->state(fn (array $attributes): array => [
            'status' => TaskStatus::Blocked,
            'blocked_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->ready()->state(fn (array $attributes): array => [
            'due_at' => now()->subHour(),
            'is_overdue' => true,
        ]);
    }

    public function siteHeld(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskStatus::Pending,
            'claimed_by_user_id' => null,
            'claimed_at' => null,
        ]);
    }
}
