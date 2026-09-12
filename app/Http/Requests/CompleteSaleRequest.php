<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sale = $this->route('sale');
        $requiresCash = $sale instanceof Sale && $sale->payment_method === PaymentMethod::CASH;

        return [
            'cash_received' => [Rule::requiredIf($requiresCash), 'nullable', 'integer', 'min:0'],
        ];
    }
}
