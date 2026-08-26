<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use App\Support\PermissionCatalog;

final class AccessCoverageService
{
    /** @return list<array{permission: string, description: string, holder_count: int}> */
    public function gaps(): array
    {
        $gaps = [];

        foreach (PermissionCatalog::COVERAGE_CHECKS as $permission) {
            $holderCount = User::permission($permission)
                ->where('active', true)
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super_admin'))
                ->count();

            if ($holderCount === 0) {
                $gaps[] = [
                    'permission' => $permission,
                    'description' => PermissionCatalog::description($permission),
                    'holder_count' => 0,
                ];
            }
        }

        return $gaps;
    }
}
