<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Task;
use App\Models\User;
use RuntimeException;

final class TaskAlreadyClaimed extends RuntimeException
{
    public static function forTask(Task $task): self
    {
        $claimant = $task->claimant;

        if ($claimant instanceof User) {
            return new self(__('This task was already claimed by :name.', ['name' => $claimant->name]));
        }

        return new self(__('This task was already claimed.'));
    }

    public function getErrorCode(): string
    {
        return 'task.already_claimed';
    }
}
