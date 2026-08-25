<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SampleStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SampleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property SampleStatus $status
 * @property int $id
 * @property string $reference
 * @property int $client_id
 * @property int|null $project_id
 * @property int|null $parent_sample_id
 * @property int|null $root_sample_id
 * @property int $attempt_number
 * @property string $color
 * @property bool $is_presale
 * @property CarbonImmutable $requested_at
 * @property Carbon|null $needed_by
 * @property-read Client $client
 * @property-read Project|null $project
 * @property-read Sample|null $parentSample
 */
final class Sample extends Model
{
    /** @use HasFactory<SampleFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'reference', 'client_id', 'project_id', 'parent_sample_id', 'root_sample_id', 'attempt_number',
        'requested_by_user_id', 'requested_at', 'needed_by', 'color', 'texture', 'client_reference', 'size',
        'finish_requirement', 'notes', 'modification_reason', 'status', 'approved_formula_id', 'is_presale',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'requested_at' => 'immutable_datetime',
            'needed_by' => 'date',
            'status' => SampleStatus::class,
            'is_presale' => 'boolean',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Sample, $this> */
    public function parentSample(): BelongsTo
    {
        return $this->belongsTo(Sample::class, 'parent_sample_id');
    }

    /** @return BelongsTo<Formula, $this> */
    public function approvedFormula(): BelongsTo
    {
        return $this->belongsTo(Formula::class, 'approved_formula_id');
    }

    /** @return HasMany<Formula, $this> */
    public function formulas(): HasMany
    {
        return $this->hasMany(Formula::class);
    }

    /** @return HasMany<SampleApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(SampleApproval::class);
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
