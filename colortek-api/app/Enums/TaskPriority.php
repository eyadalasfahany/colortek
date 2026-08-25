<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $labels = [
            'en' => [
                'low' => 'Low',
                'normal' => 'Normal',
                'high' => 'High',
                'urgent' => 'Urgent',
            ],
            'ar' => [
                'low' => 'منخفضة',
                'normal' => 'عادية',
                'high' => 'عالية',
                'urgent' => 'عاجلة',
            ],
        ];

        return $labels[$locale][$this->value] ?? $labels['en'][$this->value];
    }
}
