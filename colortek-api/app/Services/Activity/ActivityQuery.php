<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Models\ActivityEvent;
use App\Models\User;
use App\Services\Projects\ProjectVisibility;
use Illuminate\Database\Eloquent\Builder;

final class ActivityQuery
{
    public function __construct(private ProjectVisibility $visibility) {}

    /** @return Builder<ActivityEvent> */
    public function forUser(User $user): Builder
    {
        return $this->visibility->applyToActivity(
            ActivityEvent::query()->with(['actor', 'department', 'project']),
            $user,
        );
    }
}
