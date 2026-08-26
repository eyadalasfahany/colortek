<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Pagination\LengthAwarePaginator;

final class UnclaimedTaskService
{
    public function paginate(int $per = 15): LengthAwarePaginator
    {
        return Task::with(['department', 'project', 'definition'])->where('status', TaskStatus::Ready)->whereNull('claimed_by_user_id')->whereNotNull('due_at')->where('due_at', '<', now())->orderBy('due_at')->paginate($per);
    }
}
