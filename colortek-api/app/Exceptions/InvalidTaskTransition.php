<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TaskStatus;
use RuntimeException;

final class InvalidTaskTransition extends RuntimeException
{
    public static function between(TaskStatus $from, TaskStatus $to): self
    {
        return new self(__('A task cannot move from :from to :to.', [
            'from' => $from->value,
            'to' => $to->value,
        ]));
    }

    public static function notClaimant(): self
    {
        return new self(__('Only the user who claimed this task may perform that action.'));
    }
}
