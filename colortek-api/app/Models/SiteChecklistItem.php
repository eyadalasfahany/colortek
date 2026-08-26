<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChecklistAnswerType;
use Database\Factories\SiteChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ChecklistAnswerType $answer_type
 */
final class SiteChecklistItem extends Model
{
    /** @use HasFactory<SiteChecklistItemFactory> */
    use HasFactory;

    protected $fillable = ['code', 'label_en', 'label_ar', 'answer_type', 'unit', 'is_readiness_critical', 'allows_note', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['answer_type' => ChecklistAnswerType::class, 'is_readiness_critical' => 'boolean', 'allows_note' => 'boolean'];
    }

    public function localizedLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar' ? $this->label_ar : $this->label_en;
    }
}
