<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectStage: string
{
    case Lead = 'lead';
    case Quotation = 'quotation';
    case Payment = 'payment';
    case Sample = 'sample';
    case Site = 'site';
    case Production = 'production';
    case Execution = 'execution';
    case Delivery = 'delivery';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Lead => __('Lead'),
            self::Quotation => __('Quotation'),
            self::Payment => __('Payment'),
            self::Sample => __('Sample'),
            self::Site => __('Site'),
            self::Production => __('Production'),
            self::Execution => __('Execution'),
            self::Delivery => __('Delivery'),
            self::Completed => __('Completed'),
        };
    }
}
