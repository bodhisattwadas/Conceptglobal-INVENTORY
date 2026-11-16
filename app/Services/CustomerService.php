<?php

namespace App\Services;

use App\DTOs\CustomerData;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\CustomerException;
use App\Models\Customer;
use App\Models\Sale;
use Exception;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Create a new customer record.
     */
    public function createCustomer(CustomerData $data): Customer
    {
        return DB::transaction(function () use ($data) {
            try {
                return Customer::create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'phone' => $data->phone,
                    'address' => $data->address,
                    'notes' => $data->notes,
                ]);

            } catch (Exception $e) {
                throw CustomerException::creationFailed($e->getMessage(), [
                    'data' => (array) $data,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });
    }

    /**
     * Update an existing customer record.
     */
    public function updateCustomer(Customer $customer, CustomerData $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            try {
                $customer->update([
                    'name' => $data->name,
                    'email' => $data->email,
                    'phone' => $data->phone,
                    'address' => $data->address,
                    'notes' => $data->notes,
                ]);

                return $customer->refresh();

            } catch (Exception $e) {
                throw CustomerException::updateFailed($e->getMessage(), [
                    'id' => $customer->id,
                    'data' => (array) $data,
                ]);
            }
        });
    }

    /**
     * Delete a customer record.
     */
    public function deleteCustomer(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            try {
                $customer->delete();

            } catch (Exception $e) {
                throw CustomerException::deletionFailed($e->getMessage(), ['id' => $customer->id]);
            }
        });
    }

    /**
     * Retrieve customer sales transactions, line items, and key spending metrics.
     * Matches sales by customer_id or phone number in notes, identical to POS Customer Return lookup.
     */
    public function getCustomerTransactionsData(Customer $customer, ?string $search = null): array
    {
        $digits = preg_replace('/\D+/', '', (string) $customer->phone);
        $last10 = ! empty($digits) && strlen($digits) >= 10 ? substr($digits, -10) : null;

        $salesQuery = Sale::query()
            ->where(function ($q) use ($customer, $last10) {
                $q->where('customer_id', $customer->id);
                if ($last10) {
                    $q->orWhere('notes', 'like', "%{$last10}%");
                }
            })
            ->with([
                'creator',
                'items' => function ($q) {
                    $q->with([
                        'product.company',
                        'product.unit',
                        'service',
                        'inventoryMovements' => function ($m) {
                            $m->whereNotNull('purchase_item_id')->with('purchaseItem');
                        },
                    ]);
                },
            ])
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        $allSales = $salesQuery->get();

        // Calculate KPI summary metrics
        $completedSales = $allSales->where('status', SaleStatus::COMPLETED);
        $totalSpent = (float) $completedSales->sum('total');
        $salesCount = $completedSales->count();
        $totalItemsCount = (int) $completedSales->sum(fn ($sale) => $sale->items->sum('quantity'));
        $aov = $salesCount > 0 ? round($totalSpent / $salesCount, 2) : 0.0;
        $lastPurchaseDate = $completedSales->first()?->sale_date?->format('d M Y') ?? null;
        $firstPurchaseDate = $completedSales->last()?->sale_date?->format('d M Y') ?? null;

        // In-memory filter if search keyword is provided
        $filteredSales = $allSales;
        if (! blank($search)) {
            $term = mb_strtolower(trim($search));
            $filteredSales = $allSales->filter(function ($sale) use ($term) {
                if (str_contains(mb_strtolower($sale->invoice_number ?? ''), $term)) {
                    return true;
                }
                $paymentMethod = $sale->payment_method instanceof PaymentMethod
                    ? $sale->payment_method->label()
                    : (string) $sale->payment_method;
                if (str_contains(mb_strtolower($paymentMethod), $term)) {
                    return true;
                }
                foreach ($sale->items as $item) {
                    if (str_contains(mb_strtolower($item->item_name ?? ''), $term)) {
                        return true;
                    }
                    if ($item->product && str_contains(mb_strtolower($item->product->sku ?? ''), $term)) {
                        return true;
                    }
                    if ($item->product?->company && str_contains(mb_strtolower($item->product->company->company_name ?? ''), $term)) {
                        return true;
                    }
                }

                return false;
            });
        }

        $mappedSales = $filteredSales->map(function ($sale) {
            $items = $sale->items->map(function ($item) {
                $batchMovement = $item->inventoryMovements
                    ->first(fn ($m) => ! empty($m->purchaseItem?->batch_number));

                $batchNumber = $batchMovement?->purchaseItem?->batch_number
                    ?? $item->inventoryMovements->first()?->purchaseItem?->batch_number
                    ?? ($item->service_id ? 'Service' : 'N/A');

                $brandName = $item->product?->company?->company_name
                    ?? ($item->service_id ? 'Service' : 'N/A');

                $effectiveUnitPrice = $item->quantity > 0
                    ? round((float) $item->subtotal / $item->quantity, 2)
                    : (float) $item->final_price;

                return [
                    'id' => $item->id,
                    'is_product' => (bool) $item->product_id,
                    'is_service' => (bool) $item->service_id,
                    'product_id' => $item->product_id,
                    'service_id' => $item->service_id,
                    'name' => $item->item_name,
                    'brand_name' => $brandName,
                    'sku' => $item->product?->sku ?? '',
                    'unit' => $item->product?->unit?->short_name ?? ($item->service_id ? 'srv' : 'pcs'),
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount' => (float) $item->discount,
                    'final_price' => (float) $item->final_price,
                    'effective_unit_price' => $effectiveUnitPrice,
                    'subtotal' => (float) $item->subtotal,
                    'batch_number' => $batchNumber,
                ];
            });

            $paymentMethodLabel = $sale->payment_method instanceof PaymentMethod
                ? $sale->payment_method->label()
                : ucfirst((string) $sale->payment_method);

            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sale_date' => $sale->sale_date ? $sale->sale_date->format('d M Y, h:i A') : '',
                'raw_date' => $sale->sale_date ? $sale->sale_date->toIso8601String() : '',
                'status' => $sale->status instanceof SaleStatus ? $sale->status->value : (string) $sale->status,
                'status_label' => $sale->status instanceof SaleStatus ? $sale->status->label() : ucfirst((string) $sale->status),
                'payment_method' => $paymentMethodLabel,
                'is_split' => $sale->isSplit(),
                'cash_amount' => (float) ($sale->cash_amount ?? 0),
                'bank_amount' => (float) ($sale->bank_amount ?? 0),
                'subtotal' => (float) $sale->subtotal,
                'total_discount' => (float) $sale->total_discount,
                'tax' => (float) ($sale->tax ?? 0),
                'total' => (float) $sale->total,
                'formatted_total' => format_money($sale->total),
                'items_count' => (int) $sale->items->sum('quantity'),
                'items' => $items,
                'creator_name' => $sale->creator?->name ?? 'Staff',
                'show_url' => route('sales.show', $sale),
                'print_url' => route('sales.print', $sale),
            ];
        })->values();

        return [
            'metrics' => [
                'total_spent' => $totalSpent,
                'formatted_total_spent' => format_money($totalSpent),
                'sales_count' => $salesCount,
                'total_items_count' => $totalItemsCount,
                'aov' => $aov,
                'formatted_aov' => format_money($aov),
                'last_purchase_date' => $lastPurchaseDate,
                'first_purchase_date' => $firstPurchaseDate,
            ],
            'sales' => $mappedSales,
        ];
    }
}
