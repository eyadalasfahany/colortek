<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable|string|null $log_date
 * @property CarbonImmutable|null $submitted_at
 */
final class CrewLog extends Model
{
    protected $fillable = [
        'project_id',
        'log_date',
        'supervisor_user_id',
        'task_id',
        'work_done',
        'weather_note',
        'issues',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return HasMany<CrewLogMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CrewLogMember::class);
    }
}
