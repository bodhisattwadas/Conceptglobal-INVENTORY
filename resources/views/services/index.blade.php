<x-app-layout title="Services">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Services') }}
            </h2>
            <x-primary-button x-data x-on:click="$dispatch('create-service')">
                <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                {{ __('Add Service') }}
            </x-primary-button>
        </div>
    </x-slot>

    @php
        $totalServices = \App\Models\Service::count();
        $activeServices = \App\Models\Service::where('is_active', true)->count();
        $avgCost = \App\Models\Service::where('is_active', true)->avg('cost') ?? 0;
    @endphp

    <div class="py-4 space-y-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">All Services</p>
                        <p class="text-2xl font-black text-gray-900 mt-1">{{ $totalServices }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879a3 3 0 11-4.242-4.242L7.757 7.757m0 0l7.071-7.071a3 3 0 114.243 4.243L12 12"></path></svg>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-4 border border-emerald-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Active Services</p>
                        <p class="text-2xl font-black text-emerald-800 mt-1">{{ $activeServices }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-4 border border-blue-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Average Service Rate</p>
                        <p class="text-2xl font-black text-blue-800 mt-1">{{ format_money($avgCost) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm">
                        ₹
                    </div>
                </div>
            </div>

            <livewire:services.service-table />
        </div>
    </div>

    <livewire:services.service-form />
    <livewire:services.service-detail />
</x-app-layout>
