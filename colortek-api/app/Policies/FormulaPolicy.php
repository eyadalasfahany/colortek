<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Formula;
use App\Models\User;

final class FormulaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('formula.view');
    }

    public function author(User $user): bool
    {
        return $user->can('formula.author');
    }

    public function register(User $user, Formula $formula): bool
    {
        return $user->can('formula.register');
    }

    public function updateRegistered(User $user, Formula $formula): bool
    {
        return $user->can('formula.update_registered');
    }
}
