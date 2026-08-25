<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Sample;
use Illuminate\Database\Eloquent\Builder;

/** @extends BaseRepository<Sample> */
final class SampleRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Sample::class);
    }

    /** @return Builder<Sample> */
    public function baseQuery(): Builder
    {
        return $this->query();
    }

    protected function notFoundMessage(): string
    {
        return __('Sample not found');
    }
}
