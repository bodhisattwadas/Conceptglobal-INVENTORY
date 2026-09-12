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
            'global_discount' => $this->normalizeMoney($this->input('global_discount')),
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
            'cash_received' => ['nullable', 'integer', 'min:0'],
            'change' => ['nullable', 'integer', 'min:0'],
            'global_discount' => ['nullable', 'integer', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.discount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.product_id.exists' => 'The selected product does not exist.',
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
            return (int) round((float) $value);
        }

        return $value;
    }
}
