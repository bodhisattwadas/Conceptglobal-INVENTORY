<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\VendorReturn;
use App\Services\VendorReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorReturnController extends Controller
{
    public function index()
    {
        return view('vendor-returns.index');
    }

    public function create(Request $request)
    {
        $selectedPurchaseId = $request->query('purchase_id');
        $selectedPurchase = null;

        if ($selectedPurchaseId) {
            $selectedPurchase = Purchase::with(['supplier', 'company', 'items.product', 'vendorInvoice'])
                ->where('status', PurchaseStatus::RECEIVED)
                ->find($selectedPurchaseId);
        }

        // List of all received purchases that have unsold items available
        $receivedPurchases = Purchase::with(['supplier', 'company'])
            ->where('status', PurchaseStatus::RECEIVED)
            ->whereHas('items', fn ($q) => $q->where('received_quantity', '>', 0))
            ->orderByDesc('purchase_date')
            ->limit(100)
            ->get();

        return view('vendor-returns.create', compact('selectedPurchase', 'receivedPurchases'));
    }

    public function store(Request $request, VendorReturnService $service)
    {
        $validated = $request->validate([
            'purchase_id' => ['required', 'integer', 'exists:purchases,id'],
            'return_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'in:damaged,expired,defective,wrong_item,unsold_return,other'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Please select at least one item to return.',
            'items.*.quantity.min' => 'Return quantity must be at least 1.',
        ]);

        try {
            $vendorReturn = $service->createReturn($validated, Auth::id());

            return redirect()
                ->route('vendor-returns.show', $vendorReturn)
                ->with('success', "Vendor return {$vendorReturn->return_number} recorded successfully.");
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function show(VendorReturn $vendorReturn)
    {
        $vendorReturn->load(['supplier', 'company', 'purchase.vendorInvoice', 'items.product', 'creator']);

        return view('vendor-returns.show', compact('vendorReturn'));
    }

    public function print(VendorReturn $vendorReturn)
    {
        $vendorReturn->load(['supplier', 'company', 'purchase', 'items.product', 'creator']);

        return view('vendor-returns.print', compact('vendorReturn'));
    }
}
