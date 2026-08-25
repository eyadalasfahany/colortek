<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SiteChecklistItem;
use App\Models\SiteVisit;
use App\Models\SiteVisitAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteVisitAnswer>
 */
class SiteVisitAnswerFactory extends Factory
{
    protected $model = SiteVisitAnswer::class;

    public function definition(): array
    {
        return [
            'site_visit_id' => SiteVisit::factory(),
            'checklist_item_id' => SiteChecklistItem::factory(),
            'answer_value' => ['value' => true],
            'passed' => true,
            'note' => '',
        ];
    }
}
