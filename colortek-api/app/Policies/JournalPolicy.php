<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Journal;
use App\Models\User;

final class JournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('journal.view');
    }

    public function view(User $user, Journal $journal): bool
    {
        return $user->can('journal.view');
    }

    public function submit(User $user, Journal $journal): bool
    {
        return $user->can('journal.prepare');
    }

    public function reopen(User $user, Journal $journal): bool
    {
        return $user->can('journal.reopen');
    }
}
