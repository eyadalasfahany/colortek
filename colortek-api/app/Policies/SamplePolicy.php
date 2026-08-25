<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sample;
use App\Models\User;

final class SamplePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sample.view');
    }

    public function view(User $user, Sample $sample): bool
    {
        return $user->can('sample.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sample.create');
    }

    public function requestModification(User $user, Sample $sample): bool
    {
        return $user->can('sample.request_modification');
    }

    public function recordClientDecision(User $user, Sample $sample): bool
    {
        return $user->can('sample.record_client_decision');
    }
}
