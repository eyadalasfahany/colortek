<?php
declare(strict_types=1);
namespace App\Events;
use App\Models\Project;
use App\Models\User;
use App\Enums\ProjectStage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit; use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
final class ProjectStageChanged implements ShouldDispatchAfterCommit { use Dispatchable, SerializesModels; public function __construct(public \App\Models\Project $project, public \App\Models\User $user, public \App\Enums\ProjectStage $from, public \App\Enums\ProjectStage $to) {} }
