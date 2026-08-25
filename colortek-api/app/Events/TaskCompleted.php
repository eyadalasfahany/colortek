<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

final class TaskCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /** @param Collection<int, Task> $createdTasks */
    public function __construct(
        public Task $task,
        public User $user,
        public Collection $createdTasks,
    ) {}
}
