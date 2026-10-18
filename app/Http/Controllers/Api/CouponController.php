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
