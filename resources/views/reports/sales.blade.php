<x-app-layout title="Sales Reports">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight flex items-center gap-2">
                    <x-heroicon-o-chart-bar class="w-6 h-6 text-primary" />
                    <span>{{ __('Sales Reports') }}</span>
                </h2>
                <p class="text-sm text-muted-foreground mt-0.5">
                    {{ __('Monitor your sales performance, profit margins, and collections at a glance.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:reports.sales-report />
        </div>
    </div>
</x-app-layout>
