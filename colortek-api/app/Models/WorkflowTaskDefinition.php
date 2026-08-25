<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use Database\Factories\WorkflowTaskDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property list<string>|null $required_fields
 * @property list<string>|null $required_attachment_types
 * @property int|null $sla_minutes
 * @property TaskPriority|null $priority
 * @property WorkflowTemplate $template
 */
final class WorkflowTaskDefinition extends Model
{
    /** @use HasFactory<WorkflowTaskDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'template_id',
        'code',
        'title_en',
        'title_ar',
        'instructions_en',
        'instructions_ar',
        'department_id',
        'is_entry_point',
        'sla_minutes',
        'escalate_after_minutes',
        'priority',
        'requires_timer',
        'required_fields',
        'required_attachment_types',
        'form_schema',
        'blocks_when_site_not_ready',
        'auto_complete_rule',
    ];

    protected function casts(): array
    {
        return [
            'is_entry_point' => 'boolean',
            'priority' => TaskPriority::class,
            'requires_timer' => 'boolean',
            'required_fields' => 'array',
            'required_attachment_types' => 'array',
            'form_schema' => 'array',
            'blocks_when_site_not_ready' => 'boolean',
            'auto_complete_rule' => 'array',
        ];
    }

    /** @return BelongsTo<WorkflowTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'template_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<WorkflowTransition, $this> */
    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_task_definition_id');
    }

    /** @return HasMany<WorkflowTransition, $this> */
    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_task_definition_id');
    }
}
