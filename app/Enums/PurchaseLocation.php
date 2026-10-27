<?php

namespace App\Enums;

enum PurchaseLocation: string
{
    case SHOP = 'shop';
    case PARLOUR = 'parlour';

    public function label(): string
    {
        return match ($this) {
            self::SHOP => 'Shop',
            self::PARLOUR => 'Parlour',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SHOP => 'text-blue-700 bg-blue-50 border-blue-200',
            self::PARLOUR => 'text-purple-700 bg-purple-50 border-purple-200',
        };
    }
}
