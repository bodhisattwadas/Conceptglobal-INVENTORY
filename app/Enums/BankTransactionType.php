<?php

namespace App\Enums;

enum BankTransactionType: string
{
    case BankSale = 'bank_sale';
    case UpiSale = 'upi_sale';
    case CardSale = 'card_sale';
    case TransferSale = 'transfer_sale';
    case BankInvestment = 'bank_investment';
    case Expense = 'expense';
    case Drawing = 'drawing';

    public function label(): string
    {
        return match ($this) {
            self::BankSale => 'Bank Sale',
            self::UpiSale => 'UPI Sale',
            self::CardSale => 'Card Sale',
            self::TransferSale => 'Bank Transfer Sale',
            self::BankInvestment => 'Bank Investment',
            self::Expense => 'Cheque Expense',
            self::Drawing => 'Cheque Drawing',
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
            self::BankSale => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
            self::UpiSale => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
            self::CardSale => 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300 border border-teal-200 dark:border-teal-800',
            self::TransferSale => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800',
            self::BankInvestment => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
            self::Expense => 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
            self::Drawing => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
        };
    }
}
