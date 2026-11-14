<?php

namespace App\Enums;

enum CashTransactionType: string
{
    case CashSale = 'cash_sale';
    case CashInvestment = 'cash_investment';
    case Expense = 'expense';
    case Drawing = 'drawing';

    public function label(): string
    {
        return match ($this) {
            self::CashSale => 'Cash Sale',
            self::CashInvestment => 'Cash Investment',
            self::Expense => 'Expense',
            self::Drawing => 'Drawing',
        };
    }

    public function isOutflow(): bool
    {
        return in_array($this, [self::Expense, self::Drawing], true);
    }

    public function isInflow(): bool
    {
        return ! $this->isOutflow();
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::CashSale => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
            self::CashInvestment => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
            self::Expense => 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
            self::Drawing => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
        };
    }
}
