<x-app-layout title="Inventory Reconciliation">
    <x-slot name="header">
        <div class="flex justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Inventory Reconciliation') }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ __('Review and reconcile manual POS sales with master catalog and physical stock.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('inventory.index') }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
                >
                    &larr; <span>{{ __('Back to Inventory') }}</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <livewire:inventory.inventory-reconciliation-table />
        </div>

        <livewire:inventory.inventory-reconciliation-modal />
    </div>
</x-app-layout>
