<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case UPI = 'upi';
    case CARD = 'card';
    case TRANSFER = 'transfer';
    case SPLIT = 'split';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::UPI => 'UPI',
            self::CARD => 'Card',
            self::TRANSFER => 'Bank Transfer',
            self::SPLIT => 'Split (Cash + UPI)',
        };
    }
}
