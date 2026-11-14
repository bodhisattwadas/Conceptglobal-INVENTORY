<x-app-layout title="Cash &amp; Cash Equivalent">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Cash & Cash Equivalent') }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ __('Liquid cash fund register, cash sales, investments, expenses, and drawings') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:finance.cash-ledger-table />
        </div>
    </div>
</x-app-layout>
