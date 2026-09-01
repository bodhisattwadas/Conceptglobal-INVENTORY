<?php

namespace App\Livewire\Coupons;

use Livewire\Component;
use App\Models\DiscountCoupon;
use Livewire\Attributes\On;

class CouponDetail extends Component
{
    public ?DiscountCoupon $coupon = null;

    public function render()
    {
        return view('livewire.coupons.coupon-detail');
    }

    #[On('show-coupon')]
    public function show(DiscountCoupon $coupon)
    {
        $this->coupon = $coupon;
        $this->dispatch('open-modal', name: 'coupon-detail-modal');
    }

    public function closeModal()
    {
        $this->coupon = null;
    }
}
