<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

final class WorkshopDashboardService
{
    public function build(User $u): array
    {
        $d = $u->departments()->pluck('departments.id');
        $t = Task::whereIn('department_id', $d)->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])->with(['project', 'claimant'])->get();

        return ['samples_to_make' => [], 'in_progress' => $t->where('status', TaskStatus::InProgress)->values()->all(), 'formulas_to_author' => [], 'active_timers' => [], 'blocked' => $t->where('status', TaskStatus::Blocked)->values()->all(), 'ready_to_hand_back' => [], 'stub' => true];
    }
}
