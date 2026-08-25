<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalStatus: string
{
    case Open = 'open';
    case Submitted = 'submitted';
    case Accounted = 'accounted';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Submitted => __('Submitted'),
            self::Accounted => __('Accounted'),
        };
    }
}
