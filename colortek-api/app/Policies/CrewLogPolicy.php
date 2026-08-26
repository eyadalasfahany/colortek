<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CrewLog;
use App\Models\User;

final class CrewLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('time.crew_log_submit') || $user->can('time.view_all');
    }

    public function create(User $user): bool
    {
        return $user->can('time.crew_log_submit');
    }

    public function update(User $user, CrewLog $log): bool
    {
        if ($log->status === 'draft') {
            return $user->can('time.crew_log_submit');
        }

        return $user->can('time.correct');
    }

    public function submit(User $user, CrewLog $log): bool
    {
        return $user->can('time.crew_log_submit') && $log->status === 'draft';
    }
}
