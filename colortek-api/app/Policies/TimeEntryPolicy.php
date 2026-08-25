<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

final class TimeEntryPolicy
{
    public function correct(User $user, TimeEntry $entry): bool
    {
        return $user->can('time.correct');
    }
}
