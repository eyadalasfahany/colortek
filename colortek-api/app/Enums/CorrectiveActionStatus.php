<?php

declare(strict_types=1);

namespace App\Enums;

enum CorrectiveActionStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'), self::InProgress => __('In progress'), self::Resolved => __('Resolved'), self::Cancelled => __('Cancelled')
        };
    }
}
