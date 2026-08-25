<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'instance_id',
        'task_definition_id',
        'project_id',
        'subject_type',
        'subject_id',
        'title',
        'instructions',
        'department_id',
        'claimed_by_user_id',
        'claimed_at',
        'status',
        'priority',
        'due_at',
        'is_overdue',
        'escalated_at',
        'ready_at',
        'started_at',
        'completed_at',
        'completed_by_user_id',
        'active_seconds',
        'paused_seconds',
        'blocked_seconds',
        'blocker_category_id',
        'blocker_reason',
        'blocker_expected_resolution',
        'blocked_by_user_id',
        'blocked_at',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'claimed_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'is_overdue' => 'boolean',
            'escalated_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'blocker_expected_resolution' => 'immutable_date',
            'blocked_at' => 'immutable_datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function blockerCategory(): BelongsTo
    {
        return $this->belongsTo(BlockerCategory::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskDefinition::class, 'task_definition_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(TaskStatusEvent::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(TaskFieldValue::class);
    }

    public function localizedTitle(?string $locale = null): string
    {
        if (is_array($this->title)) {
            $locale ??= app()->getLocale();

            return $this->title[$locale] ?? $this->title['en'] ?? '';
        }

        return (string) $this->title;
    }
}
