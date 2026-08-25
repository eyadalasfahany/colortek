<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Sample;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** @extends BaseRepository<Sample> */
final class SampleRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Sample::class);
    }

    /** @param array<string, mixed> $data @return Sample */
    public function create(array $data): Sample
    {
        /** @var Sample $model */
        $model = parent::create($data);

        return $model;
    }

    /** @param list<string> $relations */
    public function findOneOrFail(int $id, array $relations = []): Sample
    {
        /** @var Sample|null $model */
        $model = $this->query()->with($relations)->find($id);

        if ($model === null) {
            throw new ModelNotFoundException($this->notFoundMessage());
        }

        return $model;
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
