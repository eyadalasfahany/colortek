<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CorrectiveActionStatus;
use App\Enums\ResponsibleParty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CorrectiveActionStatus $status
 * @property ResponsibleParty $responsible_party
 * @property CarbonImmutable|null $resolved_at
 */
final class CorrectiveAction extends Model
{
    protected $fillable = ['site_visit_id', 'checklist_item_id', 'description', 'responsible_party', 'task_id', 'status', 'resolution_note', 'resolved_at'];

    protected function casts(): array
    {
        return ['responsible_party' => ResponsibleParty::class, 'status' => CorrectiveActionStatus::class, 'resolved_at' => 'immutable_datetime'];
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(SiteChecklistItem::class, 'checklist_item_id');
    }

    /** @return BelongsTo<SiteVisit, $this> */
    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class, 'site_visit_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
