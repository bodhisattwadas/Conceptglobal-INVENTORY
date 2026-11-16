<x-app-layout title="Accounts Payable Vendor Aging Report">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight flex items-center gap-2">
                    <x-heroicon-o-clock class="w-6 h-6 text-primary" />
                    <span>{{ __('Accounts Payable Vendor Aging Report') }}</span>
                </h2>
                <p class="text-sm text-muted-foreground mt-0.5">
                    {{ __('Track unpaid and partially paid vendor invoices categorized by aging brackets (0–30, 31–60, 61–90, and 90+ days).') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('vendor-invoices.index') }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition-colors"
                >
                    <x-heroicon-o-arrow-left class="w-4 h-4 text-gray-500" />
                    <span>{{ __('Back to Vendor Invoices') }}</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:reports.vendor-aging-report />
        </div>
    </div>
</x-app-layout>
