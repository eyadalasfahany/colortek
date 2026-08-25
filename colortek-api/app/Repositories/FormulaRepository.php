<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Formula;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** @extends BaseRepository<Formula> */
final class FormulaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Formula::class);
    }

    /** @param list<string> $relations */
    public function findOneOrFail(int $id, array $relations = []): Formula
    {
        /** @var Formula|null $model */
        $model = $this->query()->with($relations)->find($id);

        if ($model === null) {
            throw new ModelNotFoundException($this->notFoundMessage());
        }

        return $model;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Formula
    {
        /** @var Formula $model */
        $model = parent::create($data);

        return $model;
    }

    protected function notFoundMessage(): string
    {
        return __('Formula not found');
    }
}
