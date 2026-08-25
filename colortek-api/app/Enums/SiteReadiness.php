<?php

declare(strict_types=1);

namespace App\Enums;

enum SiteReadiness: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case NotReady = 'not_ready';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Ready => __('Ready'),
            self::NotReady => __('Not ready'),
        };
    }
}
