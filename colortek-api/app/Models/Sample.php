<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SampleStatus;
use Database\Factories\SampleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function parentSample(): BelongsTo { return $this->belongsTo(Sample::class, 'parent_sample_id'); }
    public function approvedFormula(): BelongsTo { return $this->belongsTo(Formula::class, 'approved_formula_id'); }
    public function formulas(): HasMany { return $this->hasMany(Formula::class); }
    public function approvals(): HasMany { return $this->hasMany(SampleApproval::class); }
    public function attachments(): MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }
    public function workflowInstances(): MorphMany { return $this->morphMany(WorkflowInstance::class, 'subject'); }
}
