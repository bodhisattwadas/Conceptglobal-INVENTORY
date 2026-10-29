<?php

namespace App\Http\Requests;

use App\Enums\PurchaseLocation;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))->map(function ($item) {
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
            // 'status' is handled by controller (default: draft)
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
                $validator->errors()->add('items', 'Every product must belong to a brand supplied by this vendor.');
            }
        });
    }
}
