<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        Model $auditable,
        string $event,
        ?User $user,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'event' => $event,
            'user_id' => $user?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
