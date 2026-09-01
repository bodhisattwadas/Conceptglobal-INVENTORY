<?php

namespace App\Services;

use Exception;
use App\Models\DiscountCoupon;
use App\DTOs\DiscountCouponData;
use Illuminate\Support\Facades\DB;
use App\Enums\CouponType;
use App\Exceptions\DiscountCouponException;

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
     * Guard against nonsensical percentage/fixed values before persisting.
     */
    private function assertValidValue(DiscountCouponData $data): void
    {
        if ($data->type === CouponType::Percentage && $data->value > 100) {
            throw DiscountCouponException::invalidValue('Percentage value cannot exceed 100.');
        }

        if ($data->value <= 0) {
            throw DiscountCouponException::invalidValue('Coupon value must be greater than zero.');
        }

        if ($data->starts_at && $data->expires_at && $data->starts_at > $data->expires_at) {
            throw DiscountCouponException::invalidValue('Start date cannot be after expiry date.');
        }
    }
}
