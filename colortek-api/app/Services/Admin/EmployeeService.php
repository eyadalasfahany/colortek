<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Employee;
use App\Repositories\EmployeeRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EmployeeService
{
    public function __construct(private EmployeeRepository $repo) {}

    public function paginate(array $f = [], int $per = 15): LengthAwarePaginator
    {
        return $this->repo->paginateForAdmin($f, $per);
    }

    public function store(array $d): Employee
    {
        return DB::transaction(function () use ($d): Employee {
            /** @var Employee $employee */
            $employee = $this->repo->create($d);

            return $employee;
        });
    }

    public function update(Employee $e, array $d): Employee
    {
        return DB::transaction(function () use ($e, $d): Employee {
            /** @var Employee $employee */
            $employee = $this->repo->update($e, $d);

            return $employee;
        });
    }

    public function findOrFail(int $id): Employee
    {
        /** @var Employee $record */
        $record = $this->repo->findOneOrFail($id, ['department', 'user']);

        return $record;
    }
}
