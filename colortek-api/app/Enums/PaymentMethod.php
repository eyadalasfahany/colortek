<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => __('Bank transfer'),
            self::Cash => __('Cash'),
            self::Cheque => __('Cheque'),
            self::Other => __('Other'),
        };
    }
}
