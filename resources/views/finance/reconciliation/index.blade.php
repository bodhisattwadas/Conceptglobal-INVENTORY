<x-app-layout title="Month-End Reconciliation">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Month-End Reconciliation') }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ __('Reconcile physical cash counts and bank statements against recorded ledger balances') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:finance.register-reconciliation-manager />
        </div>
    </div>
</x-app-layout>
