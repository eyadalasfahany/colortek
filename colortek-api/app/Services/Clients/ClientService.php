<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Models\Client;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class ClientService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return Client::query()
            ->withCount(['projects', 'quotations'])
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): Client
    {
        $client = Client::query()->withCount(['projects', 'quotations'])->find($id);

        if ($client === null) {
            throw new ModelNotFoundException(__('Client not found'));
        }

        return $client;
    }

    /** @param array<string, mixed> $data */
    public function store(array $data, User $user): Client
    {
        return DB::transaction(function () use ($data, $user): Client {
            $client = Client::query()->create($data);
            $this->auditLogger->log($client, 'created', $user, newValues: ['name' => $client->name]);

            return $client;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Client $client, array $data, User $user): Client
    {
        return DB::transaction(function () use ($client, $data, $user): Client {
            $old = $client->only(array_keys($data));
            $client->update($data);
            $this->auditLogger->log($client, 'updated', $user, oldValues: $old, newValues: $data);

            return $client->fresh();
        });
    }
}
