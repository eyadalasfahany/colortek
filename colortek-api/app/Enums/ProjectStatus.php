<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::OnHold => __('On hold'),
            self::Cancelled => __('Cancelled'),
            self::Completed => __('Completed'),
        };
    }
}
