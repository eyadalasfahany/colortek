<?php

declare(strict_types=1);

namespace App\Enums;

enum TimeEntrySource: string
{
    case Timer = 'timer';
    case ManualCorrection = 'manual_correction';
    case AutoClosed = 'auto_closed';

    public function label(): string
    {
        return match ($this) {
            self::Timer => __('Timer'),
            self::ManualCorrection => __('Manual correction'),
            self::AutoClosed => __('Auto closed'),
        };
    }
}
