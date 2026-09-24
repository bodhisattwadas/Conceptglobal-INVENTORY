<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\DTOs\PurchaseData;
use Illuminate\Http\Request;
use App\Enums\PurchaseStatus;
use App\Services\PurchaseService;
use App\Services\VendorInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Exceptions\PurchaseException;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;

class PurchaseController extends Controller
{
    protected PurchaseService $service;

    public function __construct(PurchaseService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return view('purchases.index');
    }

    public function create()
    {
        $purchaseDate = old('purchase_date') ? Carbon::parse(old('purchase_date')) : now();

        return view('purchases.create', [
            'purchase' => new Purchase(),
            'statuses' => PurchaseStatus::cases(),
            'previewPoReference' => $this->service->previewPurchaseOrderReference($purchaseDate),
        ]);
    }

    public function previewReference(Request $request)
    {
        $validated = $request->validate([
            'purchase_date' => ['required', 'date'],
        ]);

        return response()->json([
            'reference' => $this->service->previewPurchaseOrderReference(Carbon::parse($validated['purchase_date'])),
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
        try {
            $proofPath = null;
            if ($request->hasFile('proof_image')) {
                $proofPath = $request->file('proof_image')->store('proofs', 'public');
            }

            $data = $request->validated();
            $data['proof_image'] = $proofPath;
            $data['status'] = PurchaseStatus::DRAFT->value; // Force Draft on Create

            $purchaseData = PurchaseData::fromArray($data);

            $purchase = $this->service->createPurchase($purchaseData, Auth::id());

            return redirect()->route('purchases.show', $purchase)
                ->with('success', 'Purchase created successfully.');

        } catch (PurchaseException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error creating purchase: ' . $e->getMessage());
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'company', 'creator', 'vendorInvoice', 'items.product.unit', 'items.product.company']);
        return view('purchases.show', compact('purchase'));
    }

    public function print(Purchase $purchase)
    {
        $purchase->load(['supplier', 'company', 'creator', 'vendorInvoice', 'items.product.unit', 'items.product.company']);

        $reference = $purchase->invoice_number ?: 'PO-'.$purchase->id;
        $filename = Str::slug($reference).'-purchase-order.pdf';

        return Pdf::loadView('purchases.print', compact('purchase'))
            ->setPaper('a4')
            ->download($filename);
    }

    public function receive(Purchase $purchase)
    {
        if ($purchase->status !== PurchaseStatus::ORDERED) {
            abort(403, 'Only ordered purchase orders can be received.');
        }

        $purchase->load(['supplier', 'company', 'creator', 'items.product.unit', 'items.product.company']);

        return view('purchases.receive', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        if (!in_array($purchase->status, [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED])) {
            abort(403, 'Only draft or ordered purchases can be edited.');
        }

        // Load relationships needed for the form
        $purchase->load('items.product.company', 'supplier', 'company');

        return view('purchases.edit', [
            'purchase' => $purchase,
            'statuses' => PurchaseStatus::cases(),
        ]);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase)
    {
        try {
            $proofPath = $purchase->proof_image;
            if ($request->hasFile('proof_image')) {
                $proofPath = $request->file('proof_image')->store('proofs', 'public');
            }

            $data = $request->validated();
            $data['proof_image'] = $proofPath;
            $data['status'] = $purchase->status->value; // Preserve existing status

            $purchaseData = PurchaseData::fromArray($data);

            $this->service->updatePurchase($purchase, $purchaseData);

            return redirect()->route('purchases.show', $purchase)
                ->with('success', 'Purchase updated successfully.');

        } catch (PurchaseException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error updating purchase: ' . $e->getMessage());
        }
    }

    public function destroy(Purchase $purchase)
    {
        try {
            $this->service->deletePurchase($purchase);
            return redirect()->route('purchases.index')->with('success', 'Purchase deleted successfully.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error deleting purchase: ' . $e->getMessage());
        }
    }

    public function markOrdered(Purchase $purchase)
    {
        try {
            $this->service->markAsOrdered($purchase);
            return back()->with('success', 'Purchase marked as ordered.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error marking as ordered: ' . $e->getMessage());
        }
    }

    public function markReceived(Request $request, Purchase $purchase, VendorInvoiceService $vendorInvoiceService)
    {
        $purchase->load('items');

        $rules = [
            'items' => ['required', 'array'],
            'items.*.received_quantity' => ['required', 'integer', 'min:0'],
            'items.*.manufacturing_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.product_mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
        ];

        // Only validate invoice_number if it's not already set on the purchase
        if (empty($purchase->invoice_number)) {
            $rules['invoice_number'] = 'required|string|max:255';
        }

        $rules['vendor_invoice_number'] = ['nullable', 'string', 'max:255'];
        $rules['order_received_date'] = ['required', 'date'];
        $rules['vendor_additional_discount'] = ['nullable', 'numeric'];
        $rules['proof_image'] = ['nullable', 'image', 'max:2048'];
        $rules['vendor_invoice_file'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];

        $validated = $request->validate($rules);

        try {
            $updateData = [];
            $vendorInvoicePath = null;

            if ($request->filled('invoice_number')) {
                $updateData['invoice_number'] = $request->invoice_number;
            }

            if ($request->hasFile('proof_image')) {
                $updateData['proof_image'] = $request->file('proof_image')->store('proofs', 'public');
            }

            if ($request->hasFile('vendor_invoice_file')) {
                $vendorInvoicePath = $request->file('vendor_invoice_file')->store('vendor-invoices', 'public');
            }

            if (!empty($updateData)) {
                $purchase->update($updateData);
            }

            $receivedQuantities = collect($validated['items'] ?? [])
                ->mapWithKeys(fn(array $item, int|string $itemId) => [$itemId => $item['received_quantity']])
                ->all();

            $itemReceiptDates = collect($validated['items'] ?? [])
                ->mapWithKeys(fn(array $item, int|string $itemId) => [$itemId => [
                    'order_received_date' => $validated['order_received_date'],
                    'manufacturing_date' => $item['manufacturing_date'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'product_mrp' => $item['product_mrp'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'selling_price' => $item['selling_price'] ?? null,
                ]])
                ->all();

            $this->service->markAsReceived($purchase, $receivedQuantities, $itemReceiptDates);
            $purchase->refresh()->load('items');
            $vendorInvoiceService->createFromPurchase(
                $purchase,
                $request->filled('vendor_invoice_number') ? $request->vendor_invoice_number : null,
                $vendorInvoicePath,
                $validated['order_received_date'],
                (float) ($validated['vendor_additional_discount'] ?? 0)
            );

            return redirect()->route('purchases.show', $purchase)
                ->with('success', 'Purchase received, inventory updated, and vendor invoice created.');

        } catch (PurchaseException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error receiving purchase: ' . $e->getMessage());
        }
    }

    public function cancel(Purchase $purchase)
    {
        try {
            $this->service->cancelPurchase($purchase);
            return back()->with('success', 'Purchase order cancelled.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error cancelling purchase: ' . $e->getMessage());
        }
    }

    public function markPaid(Purchase $purchase)
    {
        try {
            $this->service->markAsPaid($purchase);
            return back()->with('success', 'Purchase marked as paid.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error marking as paid: ' . $e->getMessage());
        }
    }

    public function restoreToDraft(Purchase $purchase)
    {
        try {
            $this->service->restoreToDraft($purchase);
            return back()->with('success', 'Purchase restored to draft.');
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error restoring purchase: ' . $e->getMessage());
        }
    }
}
