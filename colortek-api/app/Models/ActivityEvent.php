<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivitySeverity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property ActivitySeverity $severity
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable|null $created_at
 */
final class ActivityEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'subject_type',
        'subject_id',
        'type',
        'severity',
        'actor_user_id',
        'department_id',
        'message_en',
        'message_ar',
        'payload',
        'visible_to_permission',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => ActivitySeverity::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
