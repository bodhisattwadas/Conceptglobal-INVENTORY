<?php

namespace App\Enums;

enum ReconciliationStatus: string
{
    case Reconciled = 'reconciled';
    case Discrepancy = 'discrepancy';

    public function label(): string
    {
        return match ($this) {
            self::Reconciled => 'Reconciled',
            self::Discrepancy => 'Discrepancy',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Reconciled => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
            self::Discrepancy => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
        };
    }
}
