<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Holiday;
use Illuminate\Pagination\LengthAwarePaginator;

final class HolidayRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Holiday::class);
    }

    public function paginateOrdered(int $per = 15): LengthAwarePaginator
    {
        return $this->paginate($this->query()->with('createdBy')->orderByDesc('date'), $per);
    }

    protected function notFoundMessage(): string
    {
        return __('Holiday not found');
    }
}
