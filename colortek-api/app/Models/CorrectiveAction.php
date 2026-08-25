<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CorrectiveActionStatus;
use App\Enums\ResponsibleParty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
