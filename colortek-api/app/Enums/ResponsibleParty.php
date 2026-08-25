<?php

declare(strict_types=1);

namespace App\Enums;

enum ResponsibleParty: string
{
    case Client = 'client';
    case Contractor = 'contractor';
    case OtherTrade = 'other_trade';
    case Colortek = 'colortek';

    public function label(): string
    {
        return match ($this) {
            self::Client => __('Client'),
            self::Contractor => __('Contractor'),
            self::OtherTrade => __('Other trade'),
            self::Colortek => __('Colortek'),
        };
    }
}
