<?php

namespace App\Http\Requests;

use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $purchase = $this->route('purchase');
        $purchaseId = $purchase instanceof Purchase ? $purchase->id : $purchase;

        $rawItems = $this->input('items', []);
        if ($this->filled('items_payload')) {
            $decoded = json_decode((string) $this->input('items_payload'), true);
            if (is_array($decoded) && ! empty($decoded)) {
                $rawItems = $decoded;
            }
        }

        Log::info("[PO Update] Submission received for PO #{$purchaseId}", [
            'purchase_id' => $purchaseId,
            'user_id' => auth()->id(),
            'supplier_id' => $this->input('supplier_id'),
            'company_id' => $this->input('company_id'),
            'location' => $this->input('location'),
            'items_count' => is_array($rawItems) ? count($rawItems) : 0,
            'via_json_payload' => $this->filled('items_payload'),
        ]);

        $items = collect($rawItems)->map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }

            $submittedMrp = $item['mrp'] ?? $item['selling_price'] ?? null;
            if ($submittedMrp !== null && is_numeric($submittedMrp)) {
                $mrp = (float) $submittedMrp;
            } else {
                $mrp = (float) (Product::query()->whereKey($item['product_id'] ?? null)->value('mrp') ?? 0);
            }

            $submittedUnitPrice = isset($item['unit_price']) && is_numeric($item['unit_price']) ? (float) $item['unit_price'] : null;

            if ($submittedUnitPrice !== null && $submittedUnitPrice > $mrp) {
                $mrp = $submittedUnitPrice;
            }

            $discount = max(0, min(100, (float) ($item['discount_percent'] ?? 0)));

            if ($submittedUnitPrice !== null && (! isset($item['discount_percent']) || $item['discount_percent'] === '')) {
                $discount = $mrp > 0 ? max(0, min(100, round((1 - ($submittedUnitPrice / $mrp)) * 100, 2))) : 0;
            }

            if ($mrp > 0) {
                $calculatedUnitPrice = round($mrp * (1 - $discount / 100), 2);
                $finalUnitPrice = ($submittedUnitPrice !== null && abs($submittedUnitPrice - $calculatedUnitPrice) < 0.05)
                    ? $submittedUnitPrice
                    : $calculatedUnitPrice;

                $item['mrp'] = number_format($mrp, 2, '.', '');
                $item['unit_price'] = number_format($finalUnitPrice, 2, '.', '');
                $item['selling_price'] = number_format($mrp, 2, '.', '');
                $item['discount_percent'] = $discount;
            }

            return $item;
        })->all();

        $companyId = $this->input('company_id');
        if (! $companyId) {
            $firstProductId = collect($items)->pluck('product_id')->filter()->first();
            if ($firstProductId) {
                $companyId = Product::query()->whereKey($firstProductId)->value('company_id');
            }
        }

        $location = strtolower(trim((string) $this->input('location', '')));
        if (! in_array($location, [PurchaseLocation::SHOP->value, PurchaseLocation::PARLOUR->value], true)) {
            $location = PurchaseLocation::SHOP->value;
        }

        $this->merge([
            'items' => $items,
            'company_id' => $companyId,
            'location' => $location,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'company_id' => [
                'required',
                Rule::exists('supplier_companies', 'company_id')
                    ->where('supplier_id', $this->input('supplier_id')),
            ],
            'location' => ['required', Rule::enum(PurchaseLocation::class)],
            'purchase_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after:purchase_date'],
            'notes' => ['nullable', 'string'],
            // 'status' is preserved from existing record
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'distinct',
                Rule::exists('products', 'id'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_percent' => ['required', 'numeric', 'between:0,100'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Please add at least one item.',
            'items.*.product_id.required' => 'Product is required.',
            'items.*.product_id.distinct' => 'Each product can only be added once per purchase order.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.discount_percent.between' => 'MRP discount must be between 0% and 100%.',
            'due_date.after' => 'Expected delivery date must be after the PO date.',
            'company_id.exists' => 'The selected brand is not supplied by this vendor.',
            'items.*.product_id.exists' => 'Every product must exist in Product Master.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $purchase = $this->route('purchase');
            if (! $purchase instanceof Purchase && is_numeric($purchase)) {
                $purchase = Purchase::find($purchase);
            }
            $purchaseId = $purchase instanceof Purchase ? $purchase->id : $purchase;

            if ($purchase instanceof Purchase && $purchase->status === PurchaseStatus::RECEIVED) {
                if ((int) $this->input('supplier_id') !== (int) $purchase->supplier_id) {
                    Log::warning("[PO Update] Cannot change supplier on received PO #{$purchase->id}", [
                        'current_supplier_id' => $purchase->supplier_id,
                        'submitted_supplier_id' => $this->input('supplier_id'),
                    ]);
                    $validator->errors()->add('supplier_id', 'Supplier cannot be changed on an already received purchase order.');
                }

                $existingItems = $purchase->items->keyBy('product_id');
                $incomingItems = collect($this->input('items', []));
                $incomingProductIds = $incomingItems->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->sort()->values();
                $existingProductIds = $existingItems->keys()->map(fn ($id) => (int) $id)->sort()->values();

                if ($incomingProductIds->count() !== $existingProductIds->count() || $incomingProductIds->all() !== $existingProductIds->all()) {
                    Log::warning("[PO Update] Cannot add/remove products on received PO #{$purchase->id}", [
                        'existing_product_ids' => $existingProductIds->all(),
                        'incoming_product_ids' => $incomingProductIds->all(),
                    ]);
                    $validator->errors()->add('items', 'Products cannot be added or removed from an already received purchase order.');
                } else {
                    foreach ($incomingItems as $index => $item) {
                        $productId = (int) ($item['product_id'] ?? 0);
                        if (! $productId || ! $existingItems->has($productId)) {
                            continue;
                        }

                        $existingItem = $existingItems->get($productId);
                        $oldQty = (int) $existingItem->quantity;
                        $currentBatchStock = (int) ($existingItem->received_quantity ?? $oldQty);
                        $unitsSold = max(0, $oldQty - $currentBatchStock);
                        $newQty = (int) ($item['quantity'] ?? 0);

                        if ($newQty < $unitsSold) {
                            Log::warning("[PO Update] Cannot reduce quantity below sold units for PO #{$purchase->id}", [
                                'product_id' => $productId,
                                'new_qty' => $newQty,
                                'units_sold' => $unitsSold,
                            ]);
                            $validator->errors()->add(
                                "items.{$index}.quantity",
                                "Cannot reduce quantity below {$unitsSold} because {$unitsSold} unit(s) have already been sold from this batch."
                            );
                        }

                        $quantityDelta = $newQty - $oldQty;
                        if ($quantityDelta < 0) {
                            $product = Product::find($productId);
                            if ($product && ($product->quantity + $quantityDelta) < 0) {
                                Log::warning("[PO Update] Quantity reduction would result in negative stock for PO #{$purchase->id}", [
                                    'product_id' => $productId,
                                    'current_stock' => $product->quantity,
                                    'delta' => $quantityDelta,
                                ]);
                                $validator->errors()->add(
                                    "items.{$index}.quantity",
                                    'Cannot reduce quantity by '.abs($quantityDelta)." because only {$product->quantity} unit(s) remain in inventory."
                                );
                            }
                        }
                    }
                }
            }

            $supplierCompanyIds = collect(
                DB::table('supplier_companies')
                    ->where('supplier_id', $this->input('supplier_id'))
                    ->pluck('company_id')
            );

            $productIds = collect($this->input('items', []))
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values();

            if ($productIds->isEmpty()) {
                return;
            }

            $invalidProducts = Product::query()
                ->whereIn('id', $productIds)
                ->whereNotIn('company_id', $supplierCompanyIds)
                ->exists();

            if ($invalidProducts) {
                Log::warning("[PO Update] Rejected products not belonging to vendor's brand on PO #{$purchaseId}", [
                    'supplier_id' => $this->input('supplier_id'),
                    'allowed_company_ids' => $supplierCompanyIds->all(),
                    'submitted_product_ids' => $productIds->all(),
                ]);
                $validator->errors()->add('items', 'Every product must belong to a brand supplied by this vendor.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $purchase = $this->route('purchase');
        $purchaseId = $purchase instanceof Purchase ? $purchase->id : $purchase;

        Log::warning("[PO Update] Validation FAILED for PO #{$purchaseId}", [
            'purchase_id' => $purchaseId,
            'user_id' => auth()->id(),
            'errors' => $validator->errors()->toArray(),
            'submitted_supplier_id' => $this->input('supplier_id'),
            'submitted_company_id' => $this->input('company_id'),
            'submitted_location' => $this->input('location'),
            'submitted_items' => $this->input('items'),
        ]);

        parent::failedValidation($validator);
    }
}
