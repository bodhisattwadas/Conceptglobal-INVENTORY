<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    /**
     * Prepare incoming POS money fields for validation.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                if (array_key_exists('unit_price', $item)) {
                    $item['unit_price'] = $this->normalizeMoney($item['unit_price']);
                }

                if (array_key_exists('discount', $item)) {
                    $item['discount'] = $this->normalizeMoney($item['discount']);
                }

                return $item;
            })
            ->all();

        $this->merge([
            'cash_received' => $this->normalizeMoney($this->input('cash_received')),
            'change' => $this->normalizeMoney($this->input('change')),
            'bill_discount' => $this->normalizeMoney($this->input('bill_discount')),
            'global_discount' => $this->normalizeMoney($this->input('global_discount')),
            'round_off' => $this->normalizeMoney($this->input('round_off')),
            'items' => $items,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'sale_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'status' => ['nullable', Rule::in([SaleStatus::PENDING->value, SaleStatus::COMPLETED->value])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'change' => ['nullable', 'numeric', 'min:0'],
            'bill_discount' => ['nullable', 'numeric'],
            'global_discount' => ['nullable', 'numeric'],
            'round_off' => ['nullable', 'numeric'],
            'coupon_id' => ['nullable', 'integer', 'exists:discount_coupons,id'],
            'coupon_code' => ['nullable', 'string', 'max:50'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'required_without:items.*.service_id', 'integer', 'exists:products,id'],
            'items.*.service_id' => ['nullable', 'required_without:items.*.product_id', 'integer', 'exists:services,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $productIndices = [];
            $serviceIndices = [];

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $productId = $item['product_id'] ?? null;
                $serviceId = $item['service_id'] ?? null;

                if (! empty($productId)) {
                    if (isset($productIndices[$productId])) {
                        $validator->errors()->add("items.{$index}.product_id", 'The items.'.$index.'.product_id field has a duplicate value.');
                        $validator->errors()->add("items.{$productIndices[$productId]}.product_id", 'The items.'.$productIndices[$productId].'.product_id field has a duplicate value.');
                    } else {
                        $productIndices[$productId] = $index;
                    }
                }

                if (! empty($serviceId)) {
                    if (isset($serviceIndices[$serviceId])) {
                        $validator->errors()->add("items.{$index}.service_id", 'The items.'.$index.'.service_id field has a duplicate value.');
                        $validator->errors()->add("items.{$serviceIndices[$serviceId]}.service_id", 'The items.{$serviceIndices[$serviceId]}.service_id field has a duplicate value.');
                    } else {
                        $serviceIndices[$serviceId] = $index;
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.exists' => 'The selected product does not exist.',
            'items.*.service_id.exists' => 'The selected service does not exist.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.unit_price.min' => 'Unit price must be at least 0.',
            'items.*.discount.min' => 'Discount must be at least 0.',
        ];
    }

    private function normalizeMoney(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        return $value;
    }
}
