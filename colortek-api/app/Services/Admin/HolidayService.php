<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Jobs\RecalculateDeadlines;
use App\Models\Holiday;
use App\Models\User;
use App\Repositories\HolidayRepository;
use App\Services\Audit\AuditLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class HolidayService
{
    public function __construct(private HolidayRepository $repo, private AuditLogger $audit, private CalendarImpactService $impact) {}

    public function paginate(int $per = 15): LengthAwarePaginator
    {
        return $this->repo->paginateOrdered($per);
    }

    public function store(array $d, User $u, bool $confirm = false): Holiday
    {
        return $this->save(new Holiday, $d, $u, $confirm, 'created');
    }

    public function update(Holiday $h, array $d, User $u, bool $confirm = false): Holiday
    {
        return $this->save($h, $d, $u, $confirm, 'updated', $h->only(['date', 'name', 'type', 'is_recurring']));
    }

    public function delete(Holiday $h, User $u, bool $confirm = false): void
    {
        $c = $this->impact->countAffectedTasks(deleteHolidayId: $h->id);
        if ($c > 0 && ! $confirm) {
            abort(422, 'Confirm calendar impact.');
        } DB::transaction(function () use ($h, $u, $c) {
            $this->audit->log($h, 'deleted', $u, $h->only(['date', 'name', 'type', 'is_recurring']), null);
            $h->delete();
            if ($c > 0) {
                RecalculateDeadlines::dispatchSync();
            }
        });
    }

    public function findOrFail(int $id): Holiday
    {
        /** @var Holiday $record */
        $record = $this->repo->findOneOrFail($id, ['createdBy']);

        return $record;
    }

    private function save(Holiday $h, array $d, User $u, bool $confirm, string $event, ?array $old = null): Holiday
    {
        $c = $this->impact->countAffectedTasks(holiday: $d);
        if ($c > 0 && ! $confirm) {
            abort(422, 'Confirm calendar impact.');
        }

        return DB::transaction(function () use ($h, $d, $u, $c, $event, $old): Holiday {
            /** @var Holiday $model */
            $model = $h->exists ? $this->repo->update($h, $d) : $this->repo->create([...$d, 'created_by_user_id' => $u->id]);
            $this->audit->log($model, $event, $u, $old, $d);
            if ($c > 0) {
                RecalculateDeadlines::dispatchSync();
            }

            return $model->load('createdBy');
        });
    }
}
