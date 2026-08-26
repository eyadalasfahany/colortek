<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\JournalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $journal_date
 * @property JournalStatus $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $accounted_at
 */
final class Journal extends Model
{
    /** @use HasFactory<JournalFactory> */
    use HasFactory;

    protected $fillable = [
        'journal_date',
        'status',
        'prepared_by_user_id',
        'submitted_at',
        'accounted_by_user_id',
        'accounted_at',
        'total_amount',
        'odoo_journal_ref',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'status' => JournalStatus::class,
            'submitted_at' => 'immutable_datetime',
            'accounted_at' => 'immutable_datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function accountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accounted_by_user_id');
    }

    /** @return BelongsToMany<Payment, $this> */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'journal_payment')
            ->withPivot('amount_snapshot');
    }

    /** @return HasMany<Payment, $this> */
    public function linkedPayments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return MorphMany<WorkflowInstance, $this> */
    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(WorkflowInstance::class, 'subject');
    }

    public function recalculateTotal(): void
    {
        $total = $this->payments()->sum('journal_payment.amount_snapshot');
        $this->update(['total_amount' => $total]);
    }
}
