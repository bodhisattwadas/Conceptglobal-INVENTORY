<?php

namespace App\Services;

use App\DTOs\DiscountCouponData;
use App\Enums\CouponType;
use App\Exceptions\DiscountCouponException;
use App\Models\DiscountCoupon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DiscountCouponService
{
    /**
     * Create a new discount coupon.
     */
    public function createCoupon(DiscountCouponData $data): DiscountCoupon
    {
        return DB::transaction(function () use ($data) {
            try {
                $this->assertValidValue($data);

                return DiscountCoupon::create($data->toArray());
            } catch (Exception $e) {
                if ($e instanceof DiscountCouponException) {
                    throw $e;
                }
                throw DiscountCouponException::creationFailed($e->getMessage(), ['data' => $data->toArray()]);
            }
        });
    }

    /**
     * Update an existing discount coupon.
     */
    public function updateCoupon(DiscountCoupon $coupon, DiscountCouponData $data): DiscountCoupon
    {
        return DB::transaction(function () use ($coupon, $data) {
            try {
                $this->assertValidValue($data);

                $coupon->update($data->toArray());

                return $coupon->refresh();
            } catch (Exception $e) {
                if ($e instanceof DiscountCouponException) {
                    throw $e;
                }
                throw DiscountCouponException::updateFailed($e->getMessage(), [
                    'id' => $coupon->id,
                    'data' => $data->toArray(),
                ]);
            }
        });
    }

    /**
     * Delete a discount coupon.
     */
    public function deleteCoupon(DiscountCoupon $coupon): void
    {
        DB::transaction(function () use ($coupon) {
            try {
                if ($coupon->used_count > 0) {
                    throw new Exception('Cannot delete a coupon that has already been used.');
                }

                $coupon->delete();
            } catch (Exception $e) {
                throw DiscountCouponException::deletionFailed($e->getMessage(), ['id' => $coupon->id]);
            }
        });
    }

    /**
     * Validate coupon applicability on a given subtotal and calculate discount.
     *
     * @return array{
     *     coupon: DiscountCoupon,
     *     discount_amount: float,
     *     formatted_discount: string,
     *     type: string,
     *     value: int,
     *     message: string
     * }
     *
     * @throws DiscountCouponException
     */
    public function validateAndCalculate(DiscountCoupon|string $couponOrCode, float $subtotal): array
    {
        if (is_string($couponOrCode)) {
            $code = strtoupper(trim($couponOrCode));
            $coupon = DiscountCoupon::where('code', $code)->first();

            if (! $coupon) {
                throw DiscountCouponException::validationFailed("Coupon '{$code}' not found.");
            }
        } else {
            $coupon = $couponOrCode;
        }

        if (! $coupon->is_active) {
            throw DiscountCouponException::validationFailed("Coupon '{$coupon->code}' is currently inactive.");
        }

        $today = now()->startOfDay();

        if ($coupon->starts_at && $today->lt($coupon->starts_at->startOfDay())) {
            throw DiscountCouponException::validationFailed("Coupon '{$coupon->code}' is not valid until {$coupon->starts_at->format('d M Y')}.");
        }

        if ($coupon->expires_at && $today->gt($coupon->expires_at->startOfDay())) {
            throw DiscountCouponException::validationFailed("Coupon '{$coupon->code}' expired on {$coupon->expires_at->format('d M Y')}.");
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw DiscountCouponException::validationFailed("Coupon '{$coupon->code}' has reached its maximum usage limit ({$coupon->usage_limit}).");
        }

        if ($coupon->min_purchase_amount !== null && $subtotal < $coupon->min_purchase_amount) {
            $diff = $coupon->min_purchase_amount - $subtotal;
            throw DiscountCouponException::validationFailed(
                'Minimum cart subtotal of '.format_money($coupon->min_purchase_amount)." required for coupon '{$coupon->code}' (Add ".format_money($diff).' more).'
            );
        }

        $discount = 0.0;

        if ($coupon->type === CouponType::Percentage) {
            $raw = round(($subtotal * (float) $coupon->value) / 100, 2);
            if ($coupon->max_discount_amount !== null && $raw > $coupon->max_discount_amount) {
                $discount = (float) $coupon->max_discount_amount;
            } else {
                $discount = $raw;
            }
            $discount = min($discount, $subtotal);
            $message = "Coupon '{$coupon->code}' applied ({$coupon->value}% off".
                ($coupon->max_discount_amount ? ', max cap '.format_money($coupon->max_discount_amount) : '').
                '): saved '.format_money($discount);
        } else {
            // Flat / Fixed amount discount
            $discount = min((float) $coupon->value, $subtotal);
            $message = "Coupon '{$coupon->code}' applied (Flat ".format_money($coupon->value).' off): saved '.format_money($discount);
        }

        return [
            'coupon' => $coupon,
            'discount_amount' => $discount,
            'formatted_discount' => format_money($discount),
            'type' => $coupon->type->value,
            'value' => (int) $coupon->value,
            'message' => $message,
        ];
    }

    /**
     * Increment coupon used count.
     */
    public function incrementUsage(DiscountCoupon $coupon): void
    {
        $coupon->increment('used_count');
    }

    /**
     * Decrement coupon used count.
     */
    public function decrementUsage(DiscountCoupon $coupon): void
    {
        if ($coupon->used_count > 0) {
            $coupon->decrement('used_count');
        }
    }

    /**
     * Retrieve all currently active and unexpired coupons available for checkout.
     *
     * @return Collection<int, DiscountCoupon>
     */
    public function getAvailableCoupons(?float $subtotal = null, bool $onlyApplicable = false): Collection
    {
        $today = now()->startOfDay();

        $query = DiscountCoupon::query()
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $today);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            });

        if ($onlyApplicable && $subtotal !== null) {
            $query->where(function ($q) use ($subtotal) {
                $q->whereNull('min_purchase_amount')
                    ->orWhere('min_purchase_amount', '<=', $subtotal);
            });
        }

        return $query->orderBy('type')
            ->orderByDesc('value')
            ->get();
    }

    /**
     * Guard against nonsensical percentage/fixed values before persisting.
     */
    private function assertValidValue(DiscountCouponData $data): void
    {
        if ($data->type === CouponType::Percentage && ($data->value <= 0 || $data->value > 100)) {
            throw DiscountCouponException::invalidValue('Percentage value must be between 1 and 100.');
        }

        if ($data->type === CouponType::Fixed && $data->value <= 0) {
            throw DiscountCouponException::invalidValue('Coupon value must be greater than zero.');
        }

        if ($data->starts_at && $data->expires_at && $data->starts_at > $data->expires_at) {
            throw DiscountCouponException::invalidValue('Start date cannot be after expiry date.');
        }
    }
}
