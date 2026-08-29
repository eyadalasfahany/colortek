<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\OdooSyncLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class OdooSyncLogService
{
    /** @param array<string, mixed> $filters */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return OdooSyncLog::query()
            ->with('actor')
            ->when(
                isset($filters['operation']),
                fn ($q) => $q->where('operation', $filters['operation']),
            )
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->latest('id')
            ->paginate($perPage);
    }
}
