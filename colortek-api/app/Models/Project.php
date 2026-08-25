<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'name',
        'client_id',
        'stage',
        'status',
        'sales_user_id',
        'block_all_when_site_not_ready',
        'site_ready',
        'sla_profile',
    ];

    protected function casts(): array
    {
        return [
            'block_all_when_site_not_ready' => 'boolean',
            'site_ready' => 'boolean',
            'sla_profile' => 'array',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(WorkflowInstance::class, 'subject');
    }
}
