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

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:coupons.coupon-table />
        </div>
    </div>

    <livewire:coupons.coupon-form />
    <livewire:coupons.coupon-detail />
</x-app-layout>
