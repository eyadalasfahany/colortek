<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkflowTransition extends Model
{
    protected $fillable = [
        'template_id',
        'from_task_definition_id',
        'to_task_definition_id',
        'condition',
        'join_mode',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'condition' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'template_id');
    }

    public function fromDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskDefinition::class, 'from_task_definition_id');
    }

    public function toDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTaskDefinition::class, 'to_task_definition_id');
    }
}
