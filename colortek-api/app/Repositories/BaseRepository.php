<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 */
abstract class BaseRepository
{
    /** @param class-string<TModel> $modelClass */
    public function __construct(protected string $modelClass) {}

    /** @return TModel|null */
    public function find(int $id): ?Model
    {
        /** @var TModel|null $model */
        $model = $this->modelClass::query()->find($id);

        return $model;
    }

    /** @param list<string> $relations @return TModel */
    public function findOneOrFail(int $id, array $relations = []): Model
    {
        /** @var TModel|null $model */
        $model = $this->query()->with($relations)->find($id);

        if ($model === null) {
            throw new ModelNotFoundException($this->notFoundMessage());
        }

        return $model;
    }

    /** @param array<string, mixed> $data @return TModel */
    public function create(array $data): Model
    {
        return $this->modelClass::query()->create($data);
    }

    /** @param array<string, mixed> $data @return TModel */
    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->fresh() ?? $model;
    }

    /**
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query, int $perPage = 15)
    {
        /** @var LengthAwarePaginator<int, TModel> $paginator */
        $paginator = $query->paginate($perPage);

        return $paginator;
    }

    /** @return Builder<TModel> */
    protected function query(): Builder
    {
        /** @var Builder<TModel> $builder */
        $builder = $this->modelClass::query();

        return $builder;
    }

    abstract protected function notFoundMessage(): string;
}
