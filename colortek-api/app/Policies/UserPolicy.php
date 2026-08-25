<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('user.manage');
    }

    public function create(User $u): bool
    {
        return $u->can('user.manage');
    }

    public function update(User $u, User $m): bool
    {
        return $u->can('user.manage');
    }

    public function assignRoles(User $u, User $m): bool
    {
        return $u->can('role.assign');
    }

    public function viewEffectivePermissions(User $u, User $m): bool
    {
        return $u->can('role.manage');
    }
}
