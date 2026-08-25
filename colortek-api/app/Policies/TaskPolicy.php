<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

final class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('task.view_own_queue') || $user->can('task.view_all');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->viewAny($user);
    }

    public function claim(User $user, Task $task): bool
    {
        return $user->can('task.claim');
    }

    public function release(User $user, Task $task): bool
    {
        return $user->can('task.release');
    }

    public function start(User $user, Task $task): bool
    {
        return $user->can('task.complete');
    }

    public function pause(User $user, Task $task): bool
    {
        return $user->can('task.complete');
    }

    public function block(User $user, Task $task): bool
    {
        return $user->can('task.block');
    }

    public function complete(User $user, Task $task): bool
    {
        return $user->can('task.complete');
    }
}
