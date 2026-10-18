<x-app-layout title="Coupons">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Discount Coupons') }}
            </h2>
            <x-primary-button x-data x-on:click="$dispatch('create-coupon')">
                <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                {{ __('Create Coupon') }}
            </x-primary-button>
        </div>
    </x-slot>

    @php
        $totalCount = \App\Models\DiscountCoupon::count();
        $percentageCount = \App\Models\DiscountCoupon::where('type', \App\Enums\CouponType::Percentage)->count();
        $flatCount = \App\Models\DiscountCoupon::where('type', \App\Enums\CouponType::Fixed)->count();
    @endphp

    <div class="py-4 space-y-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">All Coupons</p>
                        <p class="text-2xl font-black text-gray-900 mt-1">{{ $totalCount }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-4 border border-emerald-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Percentage (%) Mode</p>
                        <p class="text-2xl font-black text-emerald-800 mt-1">{{ $percentageCount }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm">
                        %
                    </div>
                </div>

                <div class="bg-white rounded-xl p-4 border border-blue-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Flat / Fixed Mode</p>
                        <p class="text-2xl font-black text-blue-800 mt-1">{{ $flatCount }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm">
                        ₹
                    </div>
                </div>
            </div>

            <livewire:coupons.coupon-table />
        </div>
    </div>

    <livewire:coupons.coupon-form />
    <livewire:coupons.coupon-detail />
</x-app-layout>
