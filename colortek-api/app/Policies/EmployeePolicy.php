<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

final class EmployeePolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('employee.manage');
    }

    public function create(User $u): bool
    {
        return $u->can('employee.manage');
    }

    public function update(User $u, Employee $e): bool
    {
        return $u->can('employee.manage');
    }
}
