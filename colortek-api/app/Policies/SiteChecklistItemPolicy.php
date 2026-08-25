<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SiteChecklistItem;
use App\Models\User;

final class SiteChecklistItemPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('settings.manage');
    }

    public function create(User $u): bool
    {
        return $u->can('settings.manage');
    }

    public function update(User $u, SiteChecklistItem $i): bool
    {
        return $u->can('settings.manage');
    }
}
