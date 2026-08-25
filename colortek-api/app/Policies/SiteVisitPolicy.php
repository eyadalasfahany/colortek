<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SiteVisit;
use App\Models\User;

final class SiteVisitPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('site.view');
    }

    public function view(User $u, SiteVisit $v): bool
    {
        return $u->can('site.view');
    }

    public function create(User $u): bool
    {
        return $u->can('site.visit_create');
    }

    public function update(User $u, SiteVisit $v): bool
    {
        return $v->isSubmitted() ? $u->can('site.measurements_edit') : $u->can('site.visit_create');
    }

    public function submit(User $u, SiteVisit $v): bool
    {
        return $u->can('site.visit_submit');
    }
}
