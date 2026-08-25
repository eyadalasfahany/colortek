<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TaskStatus;
use App\Http\Filters\TaskFilter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** @extends BaseRepository<Task> */
final class TaskRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Task::class);
    }

    public function claimAtomically(int $taskId, int $userId): bool
    {
        return DB::table('tasks')
            ->where('id', $taskId)
            ->where('status', TaskStatus::Ready->value)
            ->whereNull('claimed_by_user_id')
            ->update([
                'status' => TaskStatus::Claimed->value,
                'claimed_by_user_id' => $userId,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]) === 1;
    }

    /** @param list<string> $relations */
    public function findForUser(int $id, User $user, array $relations = []): Task
    {
        $query = $this->baseQuery()->whereKey($id);

        app(TaskFilter::class)->apply(request(), $query, $user);

        /** @var Task|null $task */
        $task = $query->first();

        if ($task === null) {
            throw (new ModelNotFoundException(__('Task not found')))->setModel(Task::class, [$id]);
        }

        if ($relations !== []) {
            $task->load($relations);
        }

        return $task;
    }

    /** @return Builder<Task> */
    public function baseQuery(): Builder
    {
        return Task::query();
    }

    /**
     * @param  Builder<Task>  $query
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(Builder $query, int $perPage = 15): LengthAwarePaginator
    {
        return parent::paginate($query, $perPage);
    }

    protected function notFoundMessage(): string
    {
        return __('Task not found');
    }
}
