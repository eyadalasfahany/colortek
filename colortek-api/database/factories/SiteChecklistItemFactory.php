<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChecklistAnswerType;
use App\Models\SiteChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteChecklistItem> */
final class SiteChecklistItemFactory extends Factory
{
    protected $model = SiteChecklistItem::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'label_en' => fake()->sentence(3),
            'label_ar' => fake()->sentence(3),
            'answer_type' => ChecklistAnswerType::YesNo->value,
            'unit' => null,
            'is_readiness_critical' => false,
            'allows_note' => true,
            'sort_order' => 1,
            'active' => true,
        ];
    }
}
