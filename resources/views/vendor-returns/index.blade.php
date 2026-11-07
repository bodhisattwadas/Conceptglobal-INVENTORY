<x-app-layout title="Vendor Returns">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    {{ __('Vendor Returns / Debit Notes') }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ __('Manage product returns to vendors, stock adjustments, and debit notes') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('vendor-returns.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-rose-600 text-white hover:bg-rose-700 shadow-sm transition-colors">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    {{ __('New Vendor Return') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200">
                    <div class="flex">
                        <div class="shrink-0">
                            <x-heroicon-s-check-circle class="h-5 w-5 text-emerald-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-md bg-red-50 p-4 border border-red-200">
                    <div class="flex">
                        <div class="shrink-0">
                            <x-heroicon-s-x-circle class="h-5 w-5 text-red-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-card overflow-hidden shadow-xs sm:rounded-lg p-6">
                <livewire:vendor-returns.vendor-return-table />
            </div>
        </div>
    </div>
</x-app-layout>
