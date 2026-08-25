<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SiteVisit;

/** @extends BaseRepository<SiteVisit> */
final class SiteVisitRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(SiteVisit::class);
    }

    public function baseQuery()
    {
        return $this->query()->latest('id');
    }

    protected function notFoundMessage(): string
    {
        return __('Site visit not found');
    }
}
