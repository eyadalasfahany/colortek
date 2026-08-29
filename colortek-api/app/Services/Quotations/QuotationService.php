<?php

declare(strict_types=1);

namespace App\Services\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class QuotationService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $filters */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return Quotation::query()
            ->with('client')
            ->when(
                ! empty($filters['client_id']),
                fn ($q) => $q->where('client_id', (int) $filters['client_id']),
            )
            ->when(
                ! empty($filters['status']),
                fn ($q) => $q->where('status', $filters['status']),
            )
            ->when(
                ! empty($filters['q']),
                fn ($q) => $q->where('number', 'like', '%'.$filters['q'].'%'),
            )
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Quotation
    {
        $quotation = Quotation::query()->with('client')->find($id);

        if ($quotation === null) {
            throw new ModelNotFoundException(__('Quotation not found'));
        }

        return $quotation;
    }

    /** @param array<string, mixed> $data */
    public function store(array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($data, $user): Quotation {
            $quotation = Quotation::query()->create($data + [
                'currency' => $data['currency'] ?? 'EGP',
                'status' => $data['status'] ?? QuotationStatus::Draft->value,
            ]);

            $this->auditLogger->log($quotation, 'created', $user, newValues: ['number' => $quotation->number]);

            return $quotation->load('client');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Quotation $quotation, array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($quotation, $data, $user): Quotation {
            $old = $quotation->only(array_keys($data));
            $quotation->update($data);
            $this->auditLogger->log($quotation, 'updated', $user, oldValues: $old, newValues: $data);

            return $quotation->fresh(['client']);
        });
    }
}
