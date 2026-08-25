<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Journal;
use App\Repositories\JournalRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

final class JournalQueryService
{
    public function __construct(private JournalRepository $repository) {}

    /** @return LengthAwarePaginator<int, Journal> */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate(
            $this->repository->baseQuery()->withCount('payments')->latest('journal_date'),
            $perPage,
        );
    }

    public function findByDate(string $date): Journal
    {
        $journal = $this->repository->baseQuery()
            ->with(['payments.project', 'payments.attachments'])
            ->whereDate('journal_date', $date)
            ->first();

        if ($journal === null) {
            throw new ModelNotFoundException(__('Journal not found'));
        }

        return $journal;
    }
}
