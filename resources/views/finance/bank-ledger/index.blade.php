<x-app-layout title="Bank Register">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Bank Register') }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ __('1200 Bank Account ledger, UPI & credit card sales, bank investments, cheque expenses, and drawings') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:finance.bank-ledger-table />
        </div>
    </div>
</x-app-layout>
