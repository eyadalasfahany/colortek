<?php

declare(strict_types=1);

namespace App\Enums;

enum FormulaStatus: string
{
    case Draft = 'draft';
    case Registered = 'registered';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Registered => __('Registered'),
            self::Approved => __('Approved'),
            self::Superseded => __('Superseded'),
        };
    }
}
