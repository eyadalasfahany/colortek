<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SiteReadiness;
use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class SiteVisit extends Model
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasFactory;

    protected $fillable = [
        'reference', 'project_id', 'task_id', 'visit_number', 'parent_visit_id', 'engineer_user_id',
        'project_name_on_form', 'address_on_form', 'quotation_number_on_form', 'client_reference_note',
        'visited_on', 'readiness', 'general_notes', 'client_signatory_name', 'engineer_signed_at',
        'client_signed_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'visit_number' => 'integer', 'visited_on' => 'date', 'readiness' => SiteReadiness::class,
            'engineer_signed_at' => 'immutable_datetime', 'client_signed_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_user_id');
    }

    /** @return HasMany<SiteVisitAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(SiteVisitAnswer::class);
    }

    /** @return HasMany<SiteMeasurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(SiteMeasurement::class)->orderBy('sort_order');
    }

    /** @return HasMany<CorrectiveAction, $this> */
    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return MorphMany<WorkflowInstance, $this> */
    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(WorkflowInstance::class, 'subject');
    }
}
