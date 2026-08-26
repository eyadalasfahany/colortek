<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class AuditLogQueryService
{
    /** @return LengthAwarePaginator<int, AuditLog> */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = AuditLog::query()->with(['user'])->orderByDesc('id');

        $this->applyFilters($query, $request);

        return $query->paginate((int) $request->integer('per_page', 15));
    }

    /** @param Builder<AuditLog> $query */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->toString());
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->string('auditable_type')->toString());
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->integer('auditable_id'));
        }

        if ($request->filled('since')) {
            $query->where('created_at', '>=', $request->string('since')->toString());
        }

        if ($request->filled('until')) {
            $query->where('created_at', '<=', $request->string('until')->toString());
        }
    }
}
