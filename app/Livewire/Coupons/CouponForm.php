<?php

namespace App\Livewire\Coupons;

use Livewire\Component;
use App\Models\DiscountCoupon;
use App\Enums\CouponType;
use App\DTOs\DiscountCouponData;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use App\Services\DiscountCouponService;
use App\Exceptions\DiscountCouponException;

class CouponForm extends Component
{
    public bool $isEditing = false;
    public ?DiscountCoupon $coupon = null;

    public string $code = '';
    public string $type = 'fixed';
    public string $value = '';
    public string $min_purchase_amount = '';
    public string $max_discount_amount = '';
    public string $usage_limit = '';
    public string $starts_at = '';
    public string $expires_at = '';
    public bool $is_active = true;
    public string $description = '';

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('discount_coupons', 'code')->ignore($this->coupon?->id),
            ],
            'type' => ['required', Rule::in(array_column(CouponType::cases(), 'value'))],
            'value' => ['required', 'numeric', 'min:1'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function render()
    {
        return view('livewire.coupons.coupon-form');
    }

    #[On('create-coupon')]
    public function create(): void
    {
        $this->reset([
            'code', 'type', 'value', 'min_purchase_amount', 'max_discount_amount',
            'usage_limit', 'starts_at', 'expires_at', 'is_active', 'description',
            'coupon', 'isEditing',
        ]);
        $this->type = 'fixed';
        $this->is_active = true;
        $this->dispatch('open-modal', name: 'coupon-form-modal');
    }

    #[On('edit-coupon')]
    public function edit(DiscountCoupon $coupon): void
    {
        $this->coupon = $coupon;
        $this->code = $coupon->code;
        $this->type = $coupon->type->value;
        $this->value = (string) $coupon->value;
        $this->min_purchase_amount = (string) ($coupon->min_purchase_amount ?? '');
        $this->max_discount_amount = (string) ($coupon->max_discount_amount ?? '');
        $this->usage_limit = (string) ($coupon->usage_limit ?? '');
        $this->starts_at = $coupon->starts_at?->format('Y-m-d') ?? '';
        $this->expires_at = $coupon->expires_at?->format('Y-m-d') ?? '';
        $this->is_active = $coupon->is_active;
        $this->description = $coupon->description ?? '';
        $this->isEditing = true;
        $this->dispatch('open-modal', name: 'coupon-form-modal');
    }

    public function save(DiscountCouponService $service): void
    {
        $validated = $this->validate();

        $data = DiscountCouponData::fromArray($validated);

        try {
            if ($this->isEditing && $this->coupon) {
                $service->updateCoupon($this->coupon, $data);
                $message = 'Coupon updated successfully.';
            } else {
                $service->createCoupon($data);
                $message = 'Coupon created successfully.';
            }

            $this->dispatch('close-modal', name: 'coupon-form-modal');
            $this->dispatch('pg:eventRefresh-coupon-table');
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (DiscountCouponException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'An unexpected error occurred.', type: 'error');
        }
    }
}
