<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CustomerData;
use App\Enums\SaleStatus;
use App\Exceptions\CustomerException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q') ?? $request->input('search');
        $cacheKey = 'customers_search_'.md5($query);

        $customers = Cache::remember($cacheKey, 300, function () use ($query) {
            return Customer::query()
                ->when($query, function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->limit(20)
                ->get()
                ->map(function ($customer) {
                    return [
                        'value' => $customer->id,
                        'text' => $customer->name.($customer->phone ? ' | '.format_indian_phone($customer->phone) : ''),
                        'name' => $customer->name,
                        'phone' => $customer->phone ? format_indian_phone($customer->phone) : null,
                        'raw_phone' => $customer->phone,
                        'email' => $customer->email,
                        'address' => $customer->address,
                        'notes' => $customer->notes,
                    ];
                });
        });

        return response()->json($customers);
    }

    public function byPhone(Request $request)
    {
        $phone = trim((string) ($request->input('phone') ?? $request->input('q') ?? ''));

        if (blank($phone)) {
            return response()->json(['found' => false, 'customer' => null]);
        }

        $digits = preg_replace('/\D+/', '', $phone);
        $formatted = normalize_indian_phone($phone);

        $customer = Customer::query()
            ->where(function ($q) use ($phone, $digits, $formatted) {
                $q->where('phone', $phone);

                if ($formatted) {
                    $q->orWhere('phone', $formatted);
                }

                if (! empty($digits) && strlen($digits) >= 10) {
                    $last10 = substr($digits, -10);
                    $part1 = substr($last10, 0, 5);
                    $part2 = substr($last10, 5);
                    $q->orWhere('phone', 'like', "%{$last10}%")
                        ->orWhere('phone', 'like', "%{$part1}%{$part2}%");
                }
            })
            ->first();

        if ($customer) {
            return response()->json([
                'found' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone ? format_indian_phone($customer->phone) : $phone,
                    'raw_phone' => $customer->phone,
                    'email' => $customer->email,
                    'address' => $customer->address,
                    'notes' => $customer->notes,
                ],
            ]);
        }

        return response()->json([
            'found' => false,
            'customer' => null,
        ]);
    }

    public function salesHistory(Request $request)
    {
        $query = trim((string) (
            $request->input('invoice_number')
            ?? $request->input('query')
            ?? $request->input('phone')
            ?? $request->input('q')
            ?? ''
        ));
        $customerId = $request->input('customer_id');

        $customer = null;
        $salesByInvoice = collect();

        // 1. Check if the query matches an invoice number (exact or partial)
        if (! blank($query)) {
            $salesByInvoice = Sale::query()
                ->where('status', SaleStatus::COMPLETED)
                ->where(function ($q) use ($query) {
                    $q->where('invoice_number', $query)
                        ->orWhere('invoice_number', 'like', "%{$query}%");
                })
                ->orderByDesc('id')
                ->get();

            if ($salesByInvoice->isNotEmpty()) {
                $firstSale = $salesByInvoice->first();
                if ($firstSale->customer_id) {
                    $customer = $firstSale->customer;
                }
            }
        }

        if ($customerId && ! $customer) {
            $customer = Customer::find($customerId);
        }

        // 2. If no direct invoice match was found, check if it's a customer phone
        if (! $customer && $salesByInvoice->isEmpty() && ! blank($query)) {
            $digits = preg_replace('/\D+/', '', $query);
            $formatted = normalize_indian_phone($query);

            $customer = Customer::query()
                ->where(function ($q) use ($query, $digits, $formatted) {
                    $q->where('phone', $query);

                    if ($formatted) {
                        $q->orWhere('phone', $formatted);
                    }

                    if (! empty($digits) && strlen($digits) >= 10) {
                        $last10 = substr($digits, -10);
                        $part1 = substr($last10, 0, 5);
                        $part2 = substr($last10, 5);
                        $q->orWhere('phone', 'like', "%{$last10}%")
                            ->orWhere('phone', 'like', "%{$part1}%{$part2}%");
                    }
                })
                ->first();
        }

        $digits = preg_replace('/\D+/', '', $query);
        $last10 = ! empty($digits) && strlen($digits) >= 10 ? substr($digits, -10) : null;

        if (! $customer && ! $last10 && $salesByInvoice->isEmpty()) {
            return response()->json([
                'success' => true,
                'found' => false,
                'customer' => null,
                'sales' => [],
            ]);
        }

        // 3. Fetch completed sales for this customer (or guest bills matching phone in notes or matched invoice numbers) with products
        $salesQuery = Sale::query()
            ->where('status', SaleStatus::COMPLETED);

        if ($salesByInvoice->isNotEmpty()) {
            $salesQuery->whereIn('id', $salesByInvoice->pluck('id'));
        } else {
            $salesQuery->where(function ($q) use ($customer, $last10) {
                if ($customer) {
                    $q->where('customer_id', $customer->id);
                }
                if ($last10) {
                    $q->orWhere('notes', 'like', "%{$last10}%");
                }
            });
        }

        $sales = $salesQuery->with([
            'items' => function ($q) {
                $q->whereNotNull('product_id')
                    ->where('quantity', '>', 0)
                    ->with([
                        'product.company',
                        'product.unit',
                        'inventoryMovements' => function ($m) {
                            $m->whereNotNull('purchase_item_id')->with('purchaseItem');
                        },
                    ]);
            },
        ])
            ->orderBy('sale_date', 'desc')
            ->limit(50)
            ->get();

        $mappedSales = $sales->map(function ($sale) {
            $items = $sale->items->filter(fn ($item) => $item->product_id !== null && $item->quantity > 0)->map(function ($item) {
                // Find batch info from inventoryMovements
                $batchMovement = $item->inventoryMovements
                    ->first(fn ($m) => ! empty($m->purchaseItem?->batch_number));

                $batchNumber = $batchMovement?->purchaseItem?->batch_number
                    ?? $item->inventoryMovements->first()?->purchaseItem?->batch_number
                    ?? 'N/A';

                $purchaseItemId = $batchMovement?->purchase_item_id
                    ?? $item->inventoryMovements->first()?->purchase_item_id;

                $effectiveUnitPrice = $item->quantity > 0
                    ? round((float) $item->subtotal / $item->quantity, 2)
                    : (float) $item->final_price;

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->item_name,
                    'brand_name' => $item->product?->company?->company_name ?? 'N/A',
                    'sku' => $item->product?->sku ?? '',
                    'barcode' => $item->product?->barcode ?? '',
                    'unit' => $item->product?->unit?->short_name ?? 'pcs',
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount' => (float) $item->discount,
                    'final_price' => (float) $item->final_price,
                    'effective_unit_price' => $effectiveUnitPrice,
                    'subtotal' => (float) $item->subtotal,
                    'batch_number' => $batchNumber,
                    'purchase_item_id' => $purchaseItemId,
                ];
            })->values();

            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sale_date' => $sale->sale_date ? $sale->sale_date->format('d M Y, h:i A') : '',
                'raw_date' => $sale->sale_date ? $sale->sale_date->toIso8601String() : '',
                'payment_method' => $sale->payment_method?->label() ?? 'Cash',
                'subtotal' => (float) $sale->subtotal,
                'total_discount' => (float) $sale->total_discount,
                'total' => (float) $sale->total,
                'formatted_total' => format_money($sale->total),
                'items' => $items,
            ];
        })->filter(fn ($s) => count($s['items']) > 0)->values();

        return response()->json([
            'success' => true,
            'found' => (bool) ($customer || $mappedSales->isNotEmpty()),
            'customer' => [
                'id' => $customer?->id,
                'name' => $customer?->name ?? ($mappedSales->isNotEmpty() ? 'Customer ('.$mappedSales->first()['invoice_number'].')' : 'Customer ('.$query.')'),
                'phone' => $customer?->phone ? format_indian_phone($customer->phone) : ($digits && strlen($digits) >= 10 ? format_indian_phone($query) : ''),
                'raw_phone' => $customer?->phone ?? ($digits && strlen($digits) >= 10 ? $query : ''),
            ],
            'sales' => $mappedSales,
        ]);
    }

    public function store(Request $request, CustomerService $customerService)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => ['nullable', 'string', 'max:20', 'regex:/^\s*(?:\+91|91|0)?[\s-]*[6-9]\d{4}[\s-]*\d{5}\s*$/'],
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:500',
                'notes' => 'nullable|string|max:500',
            ]);

            $data = array_merge([
                'phone' => null,
                'email' => null,
                'address' => null,
                'notes' => null,
            ], $validated);

            $customerData = CustomerData::fromArray($data);
            $customer = $customerService->createCustomer($customerData);

            return response()->json($customer, 201);

        } catch (CustomerException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create customer: '.$e->getMessage()], 500);
        }
    }
}
