<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $response
 * @property-read User|null $actor
 */
final class OdooSyncLog extends Model
{
    protected $table = 'odoo_sync_log';

    protected $fillable = [
        'operation',
        'subject_type',
        'subject_id',
        'idempotency_key',
        'driver',
        'status',
        'payload',
        'response',
        'odoo_reference',
        'error',
        'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
