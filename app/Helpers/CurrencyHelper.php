<?php

use App\Models\Setting;

if (!function_exists('format_money')) {
    /**
     * Format a number into currency based on application settings.
     *
     * @param float|int $amount
     * @return string
     */
    function format_money($amount)
    {
        // Get settings, using comma thousands consistently across the app.
        $symbol = Setting::get('currency_symbol', 'Rp');
        $position = Setting::get('currency_position', 'left'); // 'left' or 'right'
        $fractions = (int) Setting::get('currency_fraction_digits', 2);
        $thousand = ',';
        $decimal = '.';
        
        $formattedAmount = number_format((float) $amount, $fractions, $decimal, $thousand);

        if ($position === 'left') {
            return "{$symbol} {$formattedAmount}";
        }

        if ($position === 'right') {
            return "{$formattedAmount} {$symbol}";
        }

        // Fallback
        return "{$symbol} {$formattedAmount}";
    }
}

if (!function_exists('format_indian_phone')) {
    function format_indian_phone(?string $phone): string
    {
        if (blank($phone)) {
            return '-';
        }

        $value = trim($phone);
        $digits = preg_replace('/\D+/', '', $value);

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10) {
            return '+91 '.substr($digits, 0, 5).' '.substr($digits, 5);
        }

        return $value;
    }
}

if (!function_exists('normalize_indian_phone')) {
    function normalize_indian_phone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $formatted = format_indian_phone($phone);

        return $formatted === '-' ? null : $formatted;
    }
}

if (!function_exists('public_storage_url')) {
    function public_storage_url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = ltrim($path, '/');
        $path = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

        return route('media.public', ['path' => $path]);
    }
}
