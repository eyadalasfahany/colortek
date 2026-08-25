<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Enums\ActivitySeverity;
use App\Models\ActivityEvent;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityRecorder
{
    public function record(
        string $type,
        ActivitySeverity $severity,
        string $messageEn,
        string $messageAr,
        ?User $actor = null,
        ?Project $project = null,
        ?Model $subject = null,
        ?Department $department = null,
        ?string $visibleToPermission = null,
        ?array $payload = null,
    ): ActivityEvent {
        return ActivityEvent::query()->create([
            'type' => $type,
            'severity' => $severity,
            'message_en' => $messageEn,
            'message_ar' => $messageAr,
            'actor_user_id' => $actor?->id,
            'project_id' => $project?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'department_id' => $department?->id,
            'visible_to_permission' => $visibleToPermission,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
