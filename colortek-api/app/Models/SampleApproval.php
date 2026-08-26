<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SampleApprovalDecision;
use App\Enums\SampleApprovalType;
use Carbon\CarbonImmutable;
use Database\Factories\SampleApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SampleApprovalType $type
 * @property SampleApprovalDecision|null $decision
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $form_generated_at
 */
final class SampleApproval extends Model
{
    /** @use HasFactory<SampleApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'sample_id', 'type', 'decision', 'decided_by_user_id', 'client_signatory_name', 'decided_at',
        'recorded_by_user_id', 'comments', 'form_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => SampleApprovalType::class,
            'decision' => SampleApprovalDecision::class,
            'decided_at' => 'immutable_datetime',
            'form_generated_at' => 'immutable_datetime',
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }
}
