<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowTransitionLog extends Model
{
    public $timestamps = false;

    protected $table = 'workflow_transition_log';

    protected $fillable = [
        'instance_id',
        'transition_id',
        'source_task_id',
        'taken',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'taken' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'transition_id');
    }

    public function sourceTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'source_task_id');
    }
}
