<?php

namespace App\Enums;

enum DatePeriod: string
{
    case TODAY = 'today';
    case YESTERDAY = 'yesterday';
    case THIS_WEEK = 'this_week';
    case THIS_MONTH = 'this_month';
    case LAST_MONTH = 'last_month';
    case ALL_TIME = 'all_time';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::TODAY => 'Today',
            self::YESTERDAY => 'Yesterday',
            self::THIS_WEEK => 'This Week',
            self::THIS_MONTH => 'This Month',
            self::LAST_MONTH => 'Last Month',
            self::ALL_TIME => 'All Time',
            self::CUSTOM => 'Custom Period',
        };
    }
}
