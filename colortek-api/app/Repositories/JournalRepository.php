<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Journal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/** @extends BaseRepository<Journal> */
final class JournalRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Journal::class);
    }

    /** @return Builder<Journal> */
    public function baseQuery(): Builder
    {
        return Journal::query();
    }

    /**
     * @param  Builder<Journal>  $query
     * @return LengthAwarePaginator<int, Journal>
     */
    public function paginate(Builder $query, int $perPage = 15): LengthAwarePaginator
    {
        return parent::paginate($query, $perPage);
    }

    protected function notFoundMessage(): string
    {
        return __('Journal not found');
    }
}
