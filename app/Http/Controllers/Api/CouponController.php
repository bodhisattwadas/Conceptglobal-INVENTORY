<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DiscountCouponException;
use App\Http\Controllers\Controller;
use App\Services\DiscountCouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Get all currently available coupons for checkout.
     */
    public function available(Request $request, DiscountCouponService $service): JsonResponse
    {
        $subtotal = $request->filled('subtotal') ? (float) $request->subtotal : null;
        $onlyApplicable = $request->boolean('only_applicable', false);

        $coupons = $service->getAvailableCoupons($subtotal, $onlyApplicable);

        return response()->json([
            'coupons' => $coupons->map(function ($coupon) use ($subtotal) {
                $isApplicable = $subtotal === null
                    || $coupon->min_purchase_amount === null
                    || $subtotal >= $coupon->min_purchase_amount;

                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $coupon->type->value,
                    'value' => $coupon->value,
                    'min_purchase_amount' => $coupon->min_purchase_amount,
                    'max_discount_amount' => $coupon->max_discount_amount,
                    'description' => $coupon->description,
                    'label' => $coupon->formatted_label,
                    'is_applicable' => $isApplicable,
                ];
            }),
        ]);
    }

    /**
     * Validate and calculate coupon discount for checkout.
     */
    public function apply(Request $request, DiscountCouponService $service): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $result = $service->validateAndCalculate($validated['code'], (float) $validated['subtotal']);

            return response()->json([
                'valid' => true,
                'coupon' => [
                    'id' => $result['coupon']->id,
                    'code' => $result['coupon']->code,
                    'type' => $result['type'],
                    'value' => $result['value'],
                    'min_purchase_amount' => $result['coupon']->min_purchase_amount,
                    'max_discount_amount' => $result['coupon']->max_discount_amount,
                    'description' => $result['coupon']->description,
                ],
                'discount_amount' => $result['discount_amount'],
                'formatted_discount' => $result['formatted_discount'],
                'message' => $result['message'],
            ]);
        } catch (DiscountCouponException $e) {
            return response()->json([
                'valid' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'valid' => false,
                'message' => 'An error occurred while validating the coupon.',
            ], 500);
        }
    }
}
