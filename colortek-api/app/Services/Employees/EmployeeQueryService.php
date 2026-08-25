<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class EmployeeQueryService
{
    /** @return Collection<int, Employee> */
    public function optionsForUser(User $user): Collection
    {
        $query = Employee::query()
            ->where('active', true)
            ->orderBy('name');

        if (! $user->can('employee.manage') && ! $user->can('time.view_all')) {
            $departmentIds = $user->departments()->pluck('departments.id');
            $query->whereIn('department_id', $departmentIds);
        }

        return $query->get(['id', 'code', 'name', 'department_id']);
    }
}
