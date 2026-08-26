<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TaskReassigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Task $task,
        public User $actor,
        public ?User $previousClaimant,
        public User $newClaimant,
    ) {}
}
