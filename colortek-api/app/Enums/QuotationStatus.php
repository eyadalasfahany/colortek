<?php

declare(strict_types=1);

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Locked = 'locked';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Sent => __('Sent'),
            self::Accepted => __('Accepted'),
            self::Locked => __('Locked'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
