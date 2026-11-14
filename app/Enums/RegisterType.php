<?php

namespace App\Enums;

enum RegisterType: string
{
    case Cash = 'cash';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash Register',
            self::Bank => 'Bank Register',
        };
    }

    public function accountCode(): string
    {
        return match ($this) {
            self::Cash => '1100 Cash and Cash Equivalent',
            self::Bank => '1200 Bank Account',
        };
    }
}
