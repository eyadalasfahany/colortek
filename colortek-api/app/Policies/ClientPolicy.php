<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

final class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        // Anyone who can see a project can see the client it belongs to.
        return $user->can('client.manage') || $user->can('project.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('client.manage');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('client.manage');
    }
}
