<x-app-layout title="Customers">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Customers & Transaction History') }}
            </h2>
            <x-primary-button x-data x-on:click="$dispatch('create-customer')">
                <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                {{ __('Create Customer') }}
            </x-primary-button>
        </div>
    </x-slot>

    <div class="py-4 space-y-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <!-- Dedicated Phone Number & Customer Search Bar -->
            <div
                x-data="{
                    phoneQuery: '',
                    isLoading: false,
                    customer: null,
                    sales: [],
                    salesCount: 0,
                    totalSpent: 0,
                    hasSearched: false,
                    notFound: false,

                    async searchCustomer() {
                        const q = this.phoneQuery.trim();
                        if (q.length < 3) {
                            this.resetSearch();
                            return;
                        }

                        this.isLoading = true;
                        this.hasSearched = true;
                        this.notFound = false;

                        try {
                            const res = await fetch('{{ route('ajax.customers.sales-history') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content || '',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ query: q, phone: q, full_history: true })
                            });

                            const data = await res.json();

                            if (data.success && data.customer && data.customer.id) {
                                this.customer = data.customer;
                                this.sales = data.sales || [];
                                this.salesCount = data.sales_count !== undefined
                                    ? Number(data.sales_count)
                                    : (this.sales.length || 0);
                                this.totalSpent = data.total_spent !== undefined
                                    ? Number(data.total_spent)
                                    : this.sales.reduce((sum, s) => sum + (parseFloat(s.total) || 0), 0);
                                this.notFound = false;
                            } else {
                                this.customer = null;
                                this.sales = [];
                                this.salesCount = 0;
                                this.totalSpent = 0;
                                this.notFound = true;
                            }
                        } catch (err) {
                            console.error('Customer phone search failed:', err);
                            this.notFound = true;
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    resetSearch() {
                        this.phoneQuery = '';
                        this.customer = null;
                        this.sales = [];
                        this.salesCount = 0;
                        this.totalSpent = 0;
                        this.hasSearched = false;
                        this.notFound = false;
                    },

                    formatMoney(val) {
                        return '₹ ' + parseFloat(val).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }"
                class="bg-white rounded-xl shadow-xs border border-gray-200 p-4 transition-all"
            >
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <label for="customer-phone-search" class="text-sm font-bold text-gray-900 flex items-center gap-2">
                            <x-heroicon-m-phone class="w-4 h-4 text-indigo-600" />
                            <span>{{ __('Search Customer by Phone Number or Invoice #') }}</span>
                        </label>
                        <p class="text-xs text-gray-500">
                            {{ __('Instantly look up any customer profile, lifetime purchases, and invoice transaction history.') }}
                        </p>
                    </div>

                    <!-- Search Input Box -->
                    <div class="flex items-center gap-2 w-full md:w-auto">
                        <div class="relative w-full md:w-96">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                            </div>
                            <input
                                id="customer-phone-search"
                                type="text"
                                x-model="phoneQuery"
                                @keydown.enter.prevent="searchCustomer()"
                                @input.debounce.350ms="if (phoneQuery.trim().length >= 3) searchCustomer(); else if (!phoneQuery.trim()) resetSearch();"
                                placeholder="{{ __('Enter 10-digit phone (e.g. 9876543210) or invoice #') }}"
                                class="block w-full text-xs font-semibold rounded-lg border-gray-300 shadow-2xs focus:border-indigo-500 focus:ring-indigo-500 pl-9 pr-9 py-2"
                            >
                            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center">
                                <template x-if="isLoading">
                                    <svg class="animate-spin h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </template>
                                <button
                                    type="button"
                                    x-show="!isLoading && phoneQuery"
                                    @click="resetSearch()"
                                    class="text-gray-400 hover:text-gray-600 p-0.5 rounded"
                                    title="{{ __('Clear') }}"
                                >
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="searchCustomer()"
                            :disabled="isLoading || !phoneQuery.trim()"
                            class="px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
                        >
                            {{ __('Search') }}
                        </button>
                    </div>
                </div>

                <!-- Match Found Banner Card -->
                <template x-if="customer && customer.id">
                    <div class="mt-4 p-4 rounded-xl bg-gradient-to-r from-indigo-50/70 via-white to-emerald-50/60 border border-indigo-200 flex flex-col md:flex-row md:items-center justify-between gap-4 animate-fadeIn">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-xs">
                                <span x-text="customer.name ? customer.name.charAt(0).toUpperCase() : 'C'"></span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-sm font-black text-gray-900" x-text="customer.name"></h4>
                                    <span class="text-[10px] px-2 py-0.2 rounded-full font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Matched Customer
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600 mt-0.5">
                                    <span class="font-mono font-bold text-indigo-700" x-text="customer.phone || customer.raw_phone"></span>
                                    <template x-if="customer.email">
                                        <span class="text-gray-500" x-text="customer.email"></span>
                                    </template>
                                    <span>&bull;</span>
                                    <span class="font-bold text-gray-800" x-text="(salesCount || sales.length) + ' bill(s) on record'"></span>
                                    <span>&bull;</span>
                                    <span class="font-bold text-emerald-700" x-text="'Total: ' + formatMoney(totalSpent)"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                @click="$dispatch('show-customer', { customer: customer.id })"
                                class="inline-flex items-center px-3.5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-xs transition-colors"
                            >
                                <x-heroicon-o-receipt-percent class="w-4 h-4 mr-1.5" />
                                <span>{{ __('View All Transactions') }}</span>
                            </button>
                            <a
                                :href="'{{ url('master/customers') }}/' + customer.id"
                                class="inline-flex items-center px-3 py-2 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg transition-colors"
                            >
                                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 mr-1 text-gray-500" />
                                <span>{{ __('Full Page') }}</span>
                            </a>
                            <a
                                :href="'{{ route('sales.create') }}?customer_id=' + customer.id"
                                class="inline-flex items-center px-3 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition-colors"
                            >
                                <x-heroicon-o-shopping-cart class="w-4 h-4 mr-1 text-emerald-600" />
                                <span>{{ __('New Sale') }}</span>
                            </a>
                        </div>
                    </div>
                </template>

                <!-- Not Found Banner -->
                <template x-if="hasSearched && notFound && !isLoading">
                    <div class="mt-4 p-3.5 rounded-xl bg-amber-50/70 border border-amber-200 text-xs text-amber-900 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>{{ __('No customer found matching phone or invoice "') }}<strong class="font-mono" x-text="phoneQuery"></strong>{{ __('".') }}</span>
                        </div>
                        <button
                            type="button"
                            @click="$dispatch('create-customer')"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg bg-amber-600 hover:bg-amber-700 text-white shadow-2xs transition-colors shrink-0"
                        >
                            {{ __('+ Register New Customer') }}
                        </button>
                    </div>
                </template>
            </div>

            <!-- Customer Table Component -->
            <livewire:customers.customer-table />
        </div>
    </div>

    <livewire:customers.customer-form />
    <livewire:customers.customer-detail />
</x-app-layout>
