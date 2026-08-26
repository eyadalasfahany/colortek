<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(User::class);
    }

    public function paginateForAdmin(array $f = [], int $per = 15): LengthAwarePaginator
    {
        $q = $this->query()->with(['departments', 'primaryDepartment', 'roles'])->orderBy('name');
        if (! empty($f['q'])) {
            $t = '%'.$f['q'].'%';
            $q->where(fn (Builder $b) => $b->where('name', 'like', $t)->orWhere('email', 'like', $t));
        }
        if (array_key_exists('active', $f) && $f['active'] !== null) {
            $q->where('active', filter_var($f['active'], FILTER_VALIDATE_BOOL));
        }

        return $this->paginate($q, $per);
    }

    public function countSuperAdmins(): int
    {
        return User::role('super_admin')->where('active', true)->count();
    }

    protected function notFoundMessage(): string
    {
        return __('User not found');
    }
}
