<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case Reviewed = 'reviewed';
    case Journaled = 'journaled';
    case Accounted = 'accounted';

    public function label(): string
    {
        return match ($this) {
            self::PendingConfirmation => __('Pending confirmation'),
            self::Confirmed => __('Confirmed'),
            self::Reviewed => __('Reviewed'),
            self::Journaled => __('Journaled'),
            self::Accounted => __('Accounted'),
        };
    }
}
