<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class DiscountCouponException extends Exception
{
    public static function creationFailed(string $message, array $context = []): self
    {
        Log::error("Discount coupon creation failed: {$message}", $context);
        return new self("Failed to create coupon. {$message}");
    }

    public static function updateFailed(string $message, array $context = []): self
    {
        Log::error("Discount coupon update failed: {$message}", $context);
        return new self("Failed to update coupon. {$message}");
    }

    public static function deletionFailed(string $message, array $context = []): self
    {
        Log::error("Discount coupon deletion failed: {$message}", $context);
        return new self("Failed to delete coupon. {$message}");
    }

    public static function invalidValue(string $message): self
    {
        return new self($message);
    }
}
