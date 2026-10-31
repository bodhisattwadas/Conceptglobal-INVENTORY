<?php

namespace App\Http\Controllers;

use App\DTOs\SaleData;
use App\Enums\PaymentMethod;
use App\Exceptions\SaleException;
use App\Http\Requests\CompleteSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Company;
use App\Models\Sale;
use App\Services\DiscountCouponService;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    public function index()
    {
        return view('sales.index');
    }

    public function create(DiscountCouponService $discountCouponService)
    {
        $availableCoupons = $discountCouponService->getAvailableCoupons();
        $brands = Company::query()
            ->select('id', 'company_name', 'short_name')
            ->orderBy('company_name')
            ->get();

        return view('sales.create', compact('availableCoupons', 'brands'));
    }

    public function store(StoreSaleRequest $request, SaleService $saleService)
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = Auth::id();

            $saleData = SaleData::fromArray($validated);

            $sale = $saleService->createSale($saleData);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sale created successfully',
                    'data' => $sale,
                    'print_url' => route('sales.print', $sale->id),
                    'redirect' => route('sales.create'),
                ], 201);
            }

            return redirect()->route('sales.create')
                ->with('success', 'Sale created successfully.');

        } catch (SaleException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return back()->with('error', $e->getMessage())->withInput();

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(Sale $sale)
    {
        $sale->load(['items.product.unit', 'items.inventoryMovements.purchaseItem.purchase', 'customer', 'creator']);

        return view('sales.show', compact('sale'));
    }

    public function edit(Sale $sale, DiscountCouponService $discountCouponService)
    {
        $sale->load(['items.product.unit', 'items.service', 'customer', 'creator', 'coupon']);
        $availableCoupons = $discountCouponService->getAvailableCoupons();

        if ($sale->coupon && ! $availableCoupons->contains('id', $sale->coupon->id)) {
            $availableCoupons->prepend($sale->coupon);
        }

        return view('sales.edit', compact('sale', 'availableCoupons'));
    }

    public function update(UpdateSaleRequest $request, Sale $sale, SaleService $saleService)
    {
        try {
            $validated = $request->validated();
            $validated['created_by'] = Auth::id() ?? $sale->created_by;
            $validated['status'] = $sale->status->value;
            $reason = $validated['reason'] ?? null;
            $paymentRef = $validated['payment_ref'] ?? null;

            $saleData = SaleData::fromArray($validated);

            $updatedSale = $saleService->updateHistoricalSale($sale, $saleData, $reason, $paymentRef);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Historical sale updated successfully.',
                    'data' => $updatedSale,
                    'redirect' => route('sales.show', $updatedSale->id),
                ]);
            }

            return redirect()->route('sales.show', $updatedSale->id)
                ->with('success', 'Sale updated successfully.');

        } catch (SaleException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return back()->with('error', $e->getMessage())->withInput();

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request, Sale $sale, SaleService $saleService)
    {
        try {
            $reason = $request->validate([
                'reason' => ['nullable', 'string', 'max:1000'],
            ])['reason'] ?? null;
            $saleService->cancelSale($sale, $reason);

            return redirect()->route('sales.index')->with('success', 'Sale cancelled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function print(Sale $sale)
    {
        $sale->load(['items.product.unit', 'customer', 'creator']);

        return view('sales.print', compact('sale'));
    }

    public function restore(Sale $sale, SaleService $saleService)
    {
        try {
            $saleService->restoreSale($sale);

            return redirect()->back()->with('success', 'Sale restored to Pending.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(CompleteSaleRequest $request, Sale $sale, SaleService $saleService)
    {
        try {
            $saleService->completeSale($sale, $request->validated());

            return redirect()->back()->with('success', 'Sale marked as completed.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updateDate(Request $request, Sale $sale, SaleService $saleService)
    {
        $validated = $request->validate([
            'sale_date' => ['required', 'date'],
        ]);

        try {
            $saleService->updateSaleDate($sale, $validated['sale_date']);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sale date updated successfully.',
                    'sale_date' => $sale->fresh()->sale_date->format('Y-m-d'),
                ]);
            }

            return redirect()->back()->with('success', 'Sale date updated successfully.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function updatePaymentMethod(Request $request, Sale $sale, SaleService $saleService)
    {
        $validated = $request->validate([
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'payment_ref' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $saleService->updatePaymentMethod(
                $sale,
                $validated['payment_method'],
                isset($validated['cash_received']) && $validated['cash_received'] !== '' ? (float) $validated['cash_received'] : null,
                $validated['reason'] ?? null,
                $validated['payment_ref'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment method updated successfully.',
                    'payment_method' => $sale->fresh()->payment_method->value,
                ]);
            }

            return redirect()->back()->with('success', 'Payment method updated successfully.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}
