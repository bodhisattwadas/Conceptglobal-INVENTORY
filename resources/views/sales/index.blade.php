<x-app-layout title="Sales">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Sales') }}
            </h2>
            <x-primary-button
                x-data
                x-on:click="window.location.href = '{{ route('sales.create') }}'"
            >
                <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                {{ __('Create Sale') }}
            </x-primary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:sales.sales-table />
        </div>
    </div>

    <livewire:components.delete-modal />

    <!-- Edit Sale Date Modal -->
    <x-modal name="edit-sale-date-modal" maxWidth="md">
        <div
            x-data="{
                saleId: null,
                invoice: '',
                date: '',
                actionUrl: '',
                init() {
                    window.addEventListener('open-edit-sale-date-modal', (e) => {
                        const detail = (Array.isArray(e.detail) ? e.detail[0] : e.detail) || {};
                        this.saleId = detail.saleId || null;
                        this.invoice = detail.invoice || '';
                        this.date = detail.date || '';
                        this.actionUrl = detail.actionUrl || '';
                        this.$dispatch('open-modal', { name: 'edit-sale-date-modal' });
                    });
                }
            }"
            class="p-6"
        >
            <form :action="actionUrl" method="POST">
                @csrf
                @method('PATCH')

                <div class="flex items-start justify-between border-b border-gray-100 pb-4 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Edit Sale Date') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Invoice: <span class="font-mono font-bold" x-text="invoice"></span></p>
                    </div>
                    <button
                        type="button"
                        x-on:click="$dispatch('close-modal', { name: 'edit-sale-date-modal' })"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-400 hover:text-gray-600 hover:bg-gray-50"
                    >
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="table_edit_sale_date" :value="__('Sale Date')" required hint="Select the new date for this sales record." />
                        <input
                            id="table_edit_sale_date"
                            type="date"
                            name="sale_date"
                            x-model="date"
                            required
                            class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-medium"
                        >
                    </div>

                    <div class="rounded-md bg-amber-50 p-3 border border-amber-200">
                        <div class="flex">
                            <div class="shrink-0">
                                <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-amber-800">
                                    Updating this date will also synchronize the accounting transaction date for revenue and dashboard reporting.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'edit-sale-date-modal' })">
                        {{ __('Cancel') }}
                    </x-secondary-button>

                    <x-primary-button type="submit">
                        <x-heroicon-o-check class="w-4 h-4 mr-1.5" />
                        {{ __('Save Changes') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </x-modal>
</x-app-layout>
