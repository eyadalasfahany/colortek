<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
        };
    }
}
