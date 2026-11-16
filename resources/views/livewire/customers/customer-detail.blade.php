<x-modal name="customer-detail-modal" maxWidth="6xl" focusable>
    @if($customer)
        <div class="p-6 space-y-6">
            <!-- Modal Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        {{ strtoupper(substr($customer->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-xl font-bold text-gray-900 leading-tight">
                                {{ $customer->name }}
                            </h3>
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Active Customer
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-2">
                            <span>Customer ID: #{{ $customer->id }}</span>
                            <span>&bull;</span>
                            <span>Joined {{ $customer->created_at?->format('d M Y') ?? 'N/A' }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a
                        href="{{ route('customers.show', $customer->id) }}"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition-colors"
                        title="Open full page view"
                    >
                        <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 mr-1" />
                        {{ __('Full Page') }}
                    </a>
                    <button
                        type="button"
                        x-on:click="$dispatch('close-modal', { name: 'customer-detail-modal' }); $dispatch('edit-customer', { customer: {{ $customer->id }} })"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg transition-colors"
                    >
                        <x-heroicon-o-pencil-square class="w-4 h-4 mr-1" />
                        {{ __('Edit Profile') }}
                    </button>
                    <button
                        type="button"
                        x-on:click="$dispatch('close-modal', { name: 'customer-detail-modal' })"
                        class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Customer Profile Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 bg-gray-50/70 p-4 rounded-xl border border-gray-200/80 text-xs">
                <div>
                    <span class="text-gray-500 font-medium block">Phone Number</span>
                    <div class="font-bold text-gray-900 mt-0.5 flex items-center gap-1.5">
                        <x-heroicon-m-phone class="w-3.5 h-3.5 text-indigo-600" />
                        <span class="font-mono text-sm">{{ format_indian_phone($customer->phone) }}</span>
                    </div>
                </div>

                <div>
                    <span class="text-gray-500 font-medium block">Email Address</span>
                    <div class="font-medium text-gray-800 mt-0.5 truncate flex items-center gap-1.5">
                        <x-heroicon-m-envelope class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                        <span title="{{ $customer->email }}">{{ $customer->email ?: 'Not provided' }}</span>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <span class="text-gray-500 font-medium block">Address</span>
                    <div class="font-medium text-gray-800 mt-0.5 truncate flex items-center gap-1.5">
                        <x-heroicon-m-map-pin class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                        <span title="{{ $customer->address }}">{{ $customer->address ?: 'No address specified' }}</span>
                    </div>
                </div>

                @if($customer->notes)
                    <div class="sm:col-span-2 lg:col-span-4 bg-amber-50/60 p-2.5 rounded-lg border border-amber-200/60 text-amber-900">
                        <span class="font-semibold block text-[11px] uppercase tracking-wider text-amber-700">Customer Notes</span>
                        <p class="mt-0.5 text-xs">{{ $customer->notes }}</p>
                    </div>
                @endif
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <!-- Lifetime Spend -->
                <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200 rounded-xl p-3 shadow-xs">
                    <span class="text-[11px] font-semibold text-emerald-800 uppercase tracking-wider block">Total Spent</span>
                    <div class="text-lg font-black text-emerald-950 mt-1">
                        {{ $metrics['formatted_total_spent'] ?? '₹ 0.00' }}
                    </div>
                    <span class="text-[10px] text-emerald-600 font-medium mt-0.5 block">Lifetime Purchases</span>
                </div>

                <!-- Total Invoices -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-3 shadow-xs">
                    <span class="text-[11px] font-semibold text-blue-800 uppercase tracking-wider block">Total Invoices</span>
                    <div class="text-lg font-black text-blue-950 mt-1">
                        {{ $metrics['sales_count'] ?? 0 }}
                    </div>
                    <span class="text-[10px] text-blue-600 font-medium mt-0.5 block">Completed Bills</span>
                </div>

                <!-- Items Purchased -->
                <div class="bg-gradient-to-br from-purple-50 to-violet-50 border border-purple-200 rounded-xl p-3 shadow-xs">
                    <span class="text-[11px] font-semibold text-purple-800 uppercase tracking-wider block">Items Purchased</span>
                    <div class="text-lg font-black text-purple-950 mt-1">
                        {{ $metrics['total_items_count'] ?? 0 }}
                    </div>
                    <span class="text-[10px] text-purple-600 font-medium mt-0.5 block">Total Units Bought</span>
                </div>

                <!-- Average Order Value (AOV) -->
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-3 shadow-xs">
                    <span class="text-[11px] font-semibold text-amber-800 uppercase tracking-wider block">Avg. Order Value</span>
                    <div class="text-lg font-black text-amber-950 mt-1">
                        {{ $metrics['formatted_aov'] ?? '₹ 0.00' }}
                    </div>
                    <span class="text-[10px] text-amber-600 font-medium mt-0.5 block">Per Transaction</span>
                </div>

                <!-- Last Purchase -->
                <div class="bg-gradient-to-br from-rose-50 to-pink-50 border border-rose-200 rounded-xl p-3 shadow-xs col-span-2 sm:col-span-1">
                    <span class="text-[11px] font-semibold text-rose-800 uppercase tracking-wider block">Last Purchase</span>
                    <div class="text-sm font-black text-rose-950 mt-1.5 truncate">
                        {{ $metrics['last_purchase_date'] ?? 'No purchases' }}
                    </div>
                    <span class="text-[10px] text-rose-600 font-medium mt-0.5 block">Most recent invoice</span>
                </div>
            </div>

            <!-- Transaction History Section -->
            <div class="space-y-3 pt-2">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h4 class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span>{{ __('Transaction History') }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-semibold border border-gray-200">
                                {{ $sales->count() }} {{ Str::plural('Invoice', $sales->count()) }}
                            </span>
                        </h4>
                        <p class="text-xs text-gray-500">
                            {{ __('All bills and purchased products/services (same layout as Customer Return in POS)') }}
                        </p>
                    </div>

                    <!-- Search & Controls -->
                    <div class="flex items-center gap-2">
                        <div class="relative w-64 sm:w-72">
                            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                                <x-heroicon-o-magnifying-glass class="w-3.5 h-3.5" />
                            </div>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="{{ __('Search bill #, item, brand...') }}"
                                class="block w-full text-xs rounded-lg border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 pl-8 pr-8 py-1.5"
                            >
                            @if($search)
                                <button
                                    type="button"
                                    wire:click="$set('search', '')"
                                    class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600"
                                >
                                    <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                </button>
                            @endif
                        </div>

                        @if($sales->isNotEmpty())
                            <button
                                type="button"
                                wire:click="expandAllSales(@js($sales->pluck('id')->all()))"
                                class="px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors whitespace-nowrap"
                                title="Expand All Bills"
                            >
                                {{ __('Expand All') }}
                            </button>
                            <button
                                type="button"
                                wire:click="collapseAllSales"
                                class="px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors whitespace-nowrap"
                                title="Collapse All Bills"
                            >
                                {{ __('Collapse All') }}
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Sales Invoices Accordion List -->
                @if($sales->isEmpty())
                    <div class="bg-white rounded-xl border border-gray-200 p-8 text-center space-y-2">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-400">
                            <x-heroicon-o-receipt-percent class="w-6 h-6" />
                        </div>
                        <h5 class="text-sm font-bold text-gray-700">{{ __('No transactions found') }}</h5>
                        <p class="text-xs text-gray-500 max-w-sm mx-auto">
                            @if($search)
                                {{ __('No bills match your search filter "') }}{{ $search }}{{ __('". Try a different invoice number or item name.') }}
                            @else
                                {{ __('This customer has not made any purchases yet.') }}
                            @endif
                        </p>
                    </div>
                @else
                    <div class="space-y-3 max-h-[50vh] overflow-y-auto pr-1">
                        @foreach($sales as $sale)
                            @php
                                $isExpanded = in_array($sale['id'], $expandedSales, true);
                            @endphp
                            <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden transition-all duration-200">
                                <!-- Accordion Header -->
                                <div
                                    wire:click="toggleSaleAccordion({{ $sale['id'] }})"
                                    class="p-3.5 bg-gray-50/80 hover:bg-gray-100/70 cursor-pointer flex flex-wrap items-center justify-between gap-3 transition-colors border-b border-gray-200 select-none"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                                            #
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-bold text-sm text-gray-900 font-mono tracking-tight">
                                                    {{ $sale['invoice_number'] }}
                                                </span>

                                                @if($sale['is_split'])
                                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 font-semibold border border-purple-200">
                                                        Split (Cash: {{ format_money($sale['cash_amount']) }} + Bank: {{ format_money($sale['bank_amount']) }})
                                                    </span>
                                                @else
                                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold border border-emerald-200">
                                                        {{ $sale['payment_method'] }}
                                                    </span>
                                                @endif

                                                @if($sale['status'] !== 'completed')
                                                    <span class="text-[10px] px-2 py-0.2 rounded-full bg-amber-100 text-amber-800 font-medium">
                                                        {{ $sale['status_label'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 text-xs text-gray-500 mt-0.5">
                                                <span>{{ $sale['sale_date'] }}</span>
                                                <span>&bull;</span>
                                                <span>Billed by {{ $sale['creator_name'] }}</span>
                                                <span>&bull;</span>
                                                <span>{{ $sale['items_count'] }} {{ Str::plural('item', $sale['items_count']) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <span class="text-[10px] text-gray-500 uppercase font-semibold block">Total Paid</span>
                                            <span class="text-sm font-black text-gray-900">{{ $sale['formatted_total'] }}</span>
                                        </div>

                                        <div class="text-gray-400">
                                            <x-heroicon-o-chevron-down class="w-4 h-4 transform transition-transform duration-200 {{ $isExpanded ? 'rotate-180' : '' }}" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Accordion Body (Line Items Table) -->
                                @if($isExpanded)
                                    <div class="p-3 bg-white">
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-xs">
                                                <thead class="bg-gray-50 text-gray-500 uppercase font-semibold border-b border-gray-200">
                                                    <tr>
                                                        <th class="p-2.5">Product / Item & Batch</th>
                                                        <th class="p-2.5">Brand / Company</th>
                                                        <th class="p-2.5 text-center">Qty</th>
                                                        <th class="p-2.5 text-right">Unit Price</th>
                                                        <th class="p-2.5 text-right">Discount</th>
                                                        <th class="p-2.5 text-right">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    @foreach($sale['items'] as $item)
                                                        <tr class="hover:bg-gray-50/60 transition-colors">
                                                            <!-- Product & Batch -->
                                                            <td class="p-2.5 align-middle">
                                                                <div class="font-bold text-gray-800 flex items-center gap-1.5">
                                                                    <span>{{ $item['name'] }}</span>
                                                                    @if($item['is_service'])
                                                                        <span class="text-[10px] px-1.5 py-0.2 rounded font-semibold bg-violet-100 text-violet-700 border border-violet-200">Service</span>
                                                                    @endif
                                                                </div>
                                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                                    @if(!empty($item['sku']))
                                                                        <span class="text-[10px] font-mono text-gray-500">SKU: {{ $item['sku'] }}</span>
                                                                    @endif
                                                                    @if(!empty($item['batch_number']) && $item['batch_number'] !== 'N/A')
                                                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                                                            Batch: {{ $item['batch_number'] }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </td>

                                                            <!-- Brand -->
                                                            <td class="p-2.5 align-middle text-gray-600 font-medium">
                                                                {{ $item['brand_name'] }}
                                                            </td>

                                                            <!-- Qty -->
                                                            <td class="p-2.5 align-middle text-center font-bold text-gray-800 whitespace-nowrap">
                                                                {{ $item['quantity'] }} {{ $item['unit'] }}
                                                            </td>

                                                            <!-- Unit Price -->
                                                            <td class="p-2.5 align-middle text-right font-medium text-gray-700 whitespace-nowrap">
                                                                {{ format_money($item['unit_price']) }}
                                                            </td>

                                                            <!-- Discount -->
                                                            <td class="p-2.5 align-middle text-right text-gray-500 whitespace-nowrap">
                                                                @if($item['discount'] > 0)
                                                                    <span class="text-rose-600 font-medium">-{{ format_money($item['discount']) }}</span>
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>

                                                            <!-- Subtotal -->
                                                            <td class="p-2.5 align-middle text-right font-bold text-gray-900 whitespace-nowrap">
                                                                {{ format_money($item['subtotal']) }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Bill Footer & Quick Actions -->
                                        <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 bg-gray-50/50 p-2.5 rounded-lg">
                                            <div class="flex items-center gap-2">
                                                <a
                                                    href="{{ $sale['show_url'] }}"
                                                    target="_blank"
                                                    class="inline-flex items-center px-2.5 py-1 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-100 border border-gray-300 rounded-md shadow-2xs transition-colors"
                                                    title="View Full Invoice"
                                                >
                                                    <x-heroicon-o-document-text class="w-3.5 h-3.5 mr-1 text-gray-500" />
                                                    {{ __('View Invoice') }}
                                                </a>
                                                <a
                                                    href="{{ $sale['print_url'] }}"
                                                    target="_blank"
                                                    class="inline-flex items-center px-2.5 py-1 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-100 border border-gray-300 rounded-md shadow-2xs transition-colors"
                                                    title="Print Receipt"
                                                >
                                                    <x-heroicon-o-printer class="w-3.5 h-3.5 mr-1 text-gray-500" />
                                                    {{ __('Print Receipt') }}
                                                </a>
                                                <a
                                                    href="{{ route('sales.create') }}?return_query={{ urlencode($sale['invoice_number']) }}"
                                                    class="inline-flex items-center px-2.5 py-1 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-md transition-colors"
                                                    title="Initiate Return for this Bill"
                                                >
                                                    <x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5 mr-1 text-rose-600" />
                                                    {{ __('Initiate Return') }}
                                                </a>
                                            </div>

                                            <div class="flex items-center gap-4 text-xs">
                                                @if($sale['total_discount'] > 0)
                                                    <span class="text-gray-500">
                                                        Total Disc: <strong class="text-rose-600 font-semibold">-{{ format_money($sale['total_discount']) }}</strong>
                                                    </span>
                                                @endif
                                                <span class="text-gray-700">
                                                    Bill Net Total: <strong class="text-gray-900 font-black text-sm">{{ $sale['formatted_total'] }}</strong>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Footer Actions -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <a
                    href="{{ route('sales.create') }}?customer_id={{ $customer->id }}"
                    class="inline-flex items-center px-3.5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors"
                >
                    <x-heroicon-o-shopping-cart class="w-4 h-4 mr-1.5" />
                    {{ __('Create New Sale for Customer') }}
                </a>

                <div class="flex items-center gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'customer-detail-modal' })">
                        {{ __('Close') }}
                    </x-secondary-button>
                </div>
            </div>
        </div>
    @else
        <div class="p-8 text-center flex flex-col items-center justify-center space-y-3">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            <span class="text-sm font-medium text-gray-500">{{ __('Loading customer details & transactions...') }}</span>
        </div>
    @endif
</x-modal>
