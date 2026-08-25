<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteVisitAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SiteVisitAnswer extends Model
{
    /** @use HasFactory<SiteVisitAnswerFactory> */
    use HasFactory;

    protected $fillable = ['site_visit_id', 'checklist_item_id', 'answer_value', 'passed', 'note'];

    protected function casts(): array
    {
        return ['answer_value' => 'array', 'passed' => 'boolean'];
    }

    /** @return BelongsTo<SiteChecklistItem, $this> */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(SiteChecklistItem::class, 'checklist_item_id');
    }
}
