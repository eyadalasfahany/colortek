<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleApprovalType: string
{
    case Manager = 'manager';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Manager => __('Manager'),
            self::Client => __('Client'),
        };
    }
}
