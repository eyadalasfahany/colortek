<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ProjectStage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProjectStageChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Project $project, public User $user, public ProjectStage $from, public ProjectStage $to) {}
}
