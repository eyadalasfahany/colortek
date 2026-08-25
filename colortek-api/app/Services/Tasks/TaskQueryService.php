<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Http\Filters\TaskFilter;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class TaskQueryService
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskFilter $filter,
    ) {}

    /** @return LengthAwarePaginator<int, Task> */
    public function paginateForUser(Request $request, User $user): LengthAwarePaginator
    {
        $query = $this->tasks->baseQuery()->with(['department', 'claimant']);

        $this->filter->apply($request, $query, $user);

        return $this->tasks->paginate($query, (int) $request->integer('per_page', 15));
    }

    public function findForUser(int $id, User $user): Task
    {
        return $this->tasks->findForUser($id, $user, ['department', 'claimant', 'project', 'definition']);
    }
}
