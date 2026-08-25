<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkflowTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkflowTemplate extends Model
{
    /** @use HasFactory<WorkflowTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'version',
        'name_en',
        'name_ar',
        'scope',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<WorkflowTaskDefinition, $this> */
    public function definitions(): HasMany
    {
        return $this->hasMany(WorkflowTaskDefinition::class, 'template_id');
    }

    /** @return HasMany<WorkflowTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'template_id');
    }

    /** @return HasMany<WorkflowInstance, $this> */
    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class, 'template_id');
    }
}
