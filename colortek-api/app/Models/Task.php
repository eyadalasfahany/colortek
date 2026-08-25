<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $instance_id
 * @property int|null $task_definition_id
 * @property int|null $project_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|array<string, string>|null $title
 * @property string|array<string, string>|null $instructions
 * @property int $department_id
 * @property int|null $claimed_by_user_id
 * @property CarbonImmutable|null $claimed_at
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property CarbonImmutable|null $due_at
 * @property bool $is_overdue
 * @property CarbonImmutable|null $escalated_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property int|null $completed_by_user_id
 * @property Department $department
 * @property User|null $claimant
 * @property Project|null $project
 */
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

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /** @return BelongsTo<BlockerCategory, $this> */
    public function blockerCategory(): BelongsTo
    {
        return $this->belongsTo(BlockerCategory::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    /** @return BelongsTo<WorkflowTaskDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskDefinition::class, 'task_definition_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<TaskStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(TaskStatusEvent::class);
    }

    /** @return HasMany<TaskFieldValue, $this> */
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
