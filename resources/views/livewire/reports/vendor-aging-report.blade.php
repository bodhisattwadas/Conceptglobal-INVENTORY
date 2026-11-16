<div class="space-y-6">
    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4">
        <!-- Total Outstanding Due -->
        <div class="rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-blue-700">{{ __('Total Due') }}</span>
                <span class="rounded-full bg-blue-100 p-1 text-blue-600">
                    <x-heroicon-o-banknotes class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-2 text-xl font-bold text-blue-900">
                {{ format_money($kpis['total_due']) }}
            </div>
            <p class="mt-1 text-xs text-blue-600">
                {{ $kpis['invoices_count'] }} {{ __('open invoice(s)') }}
            </p>
        </div>

        <!-- 0–30 Days (Current) -->
        <div class="rounded-xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">{{ __('0–30 Days') }}</span>
                <span class="rounded-full bg-emerald-100 p-1 text-emerald-600">
                    <x-heroicon-o-check-circle class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-2 text-xl font-bold text-emerald-900">
                {{ format_money($kpis['bucket_0_30_due']) }}
            </div>
            <p class="mt-1 text-xs text-emerald-600">{{ __('Current / Prompt') }}</p>
        </div>

        <!-- 31–60 Days -->
        <div class="rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-700">{{ __('31–60 Days') }}</span>
                <span class="rounded-full bg-amber-100 p-1 text-amber-600">
                    <x-heroicon-o-clock class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-2 text-xl font-bold text-amber-900">
                {{ format_money($kpis['bucket_31_60_due']) }}
            </div>
            <p class="mt-1 text-xs text-amber-600">{{ __('Past Due (1 Mo.)') }}</p>
        </div>

        <!-- 61–90 Days -->
        <div class="rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50 to-white p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-orange-700">{{ __('61–90 Days') }}</span>
                <span class="rounded-full bg-orange-100 p-1 text-orange-600">
                    <x-heroicon-o-exclamation-circle class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-2 text-xl font-bold text-orange-900">
                {{ format_money($kpis['bucket_61_90_due']) }}
            </div>
            <p class="mt-1 text-xs text-orange-600">{{ __('Past Due (2 Mos.)') }}</p>
        </div>

        <!-- 90+ Days -->
        <div class="rounded-xl border border-rose-200 bg-gradient-to-br from-rose-50 to-white p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-rose-700">{{ __('90+ Days') }}</span>
                <span class="rounded-full bg-rose-100 p-1 text-rose-600">
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-2 text-xl font-bold text-rose-900">
                {{ format_money($kpis['bucket_90_plus_due']) }}
            </div>
            <p class="mt-1 text-xs text-rose-600">{{ __('Severely Overdue') }}</p>
        </div>

        <!-- Invoices Count -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Unpaid Invoices') }}</span>
                <span class="rounded-full bg-gray-100 p-1 text-gray-600">
                    <x-heroicon-o-document-text class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-2 text-xl font-bold text-gray-900">
                {{ $kpis['invoices_count'] }}
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ __('Pending settlement') }}</p>
        </div>

        <!-- Vendors Count -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Vendors') }}</span>
                <span class="rounded-full bg-gray-100 p-1 text-gray-600">
                    <x-heroicon-o-building-storefront class="h-4 w-4" />
                </span>
            </div>
            <div class="mt-2 text-xl font-bold text-gray-900">
                {{ $kpis['vendors_count'] }}
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ __('Distinct suppliers') }}</p>
        </div>
    </div>

    <!-- Filters and Action Toolbar -->
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Filter Controls -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 flex-1">
                <!-- Search input -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Search') }}</label>
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="{{ __('Search invoice #, PO, vendor...') }}"
                            class="w-full rounded-md border-gray-300 py-1.5 pl-8 pr-3 text-sm focus:border-primary focus:ring-primary shadow-xs"
                        />
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" />
                    </div>
                </div>

                <!-- Vendor / Supplier Select -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Vendor / Supplier') }}</label>
                    <select
                        wire:model.live="supplierId"
                        class="w-full rounded-md border-gray-300 py-1.5 text-sm focus:border-primary focus:ring-primary shadow-xs"
                    >
                        <option value="all">{{ __('All Vendors') }}</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Aging Bucket Select -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Aging Bracket') }}</label>
                    <select
                        wire:model.live="bucket"
                        class="w-full rounded-md border-gray-300 py-1.5 text-sm focus:border-primary focus:ring-primary shadow-xs"
                    >
                        <option value="all">{{ __('All Brackets') }}</option>
                        <option value="0-30">{{ __('0–30 Days (Current)') }}</option>
                        <option value="31-60">{{ __('31–60 Days') }}</option>
                        <option value="61-90">{{ __('61–90 Days') }}</option>
                        <option value="90+">{{ __('90+ Days') }}</option>
                    </select>
                </div>

                <!-- Rows Per Page -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Per Page') }}</label>
                    <select
                        wire:model.live="perPage"
                        class="w-full rounded-md border-gray-300 py-1.5 text-sm focus:border-primary focus:ring-primary shadow-xs"
                    >
                        <option value="15">15 {{ __('rows') }}</option>
                        <option value="25">25 {{ __('rows') }}</option>
                        <option value="50">50 {{ __('rows') }}</option>
                        <option value="100">100 {{ __('rows') }}</option>
                        <option value="all">{{ __('Show All') }}</option>
                    </select>
                </div>
            </div>

            <!-- Export and Reset Actions -->
            <div class="flex items-center gap-2 self-end lg:self-center">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-xs"
                    title="{{ __('Reset filters') }}"
                >
                    <x-heroicon-o-arrow-path class="w-4 h-4 text-gray-500" />
                    <span>{{ __('Reset') }}</span>
                </button>

                <!-- PDF Export Button -->
                <a
                    href="{{ route('reports.vendor-aging.pdf', ['supplier_id' => $supplierId, 'bucket' => $bucket, 'search' => $search]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100 shadow-xs transition-colors"
                >
                    <x-heroicon-o-document-arrow-down class="w-4 h-4 text-red-600" />
                    <span>{{ __('Export PDF') }}</span>
                </a>

                <!-- CSV Export Button -->
                <a
                    href="{{ route('reports.vendor-aging.csv', ['supplier_id' => $supplierId, 'bucket' => $bucket, 'search' => $search]) }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-100 shadow-xs transition-colors"
                >
                    <x-heroicon-o-table-cells class="w-4 h-4 text-emerald-600" />
                    <span>{{ __('Export CSV') }}</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Vendor Summary Breakdown Table -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                <x-heroicon-o-building-office-2 class="w-4 h-4 text-primary" />
                <span>{{ __('Vendor Summary Breakdown') }}</span>
            </h3>
            <span class="text-xs text-gray-500 font-medium">
                {{ count($vendorBreakdown) }} {{ __('vendor(s) with outstanding dues') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Vendor / Supplier') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Contact') }}</th>
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Invoices') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-emerald-700 uppercase tracking-wider">{{ __('0–30 Days') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-amber-700 uppercase tracking-wider">{{ __('31–60 Days') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-orange-700 uppercase tracking-wider">{{ __('61–90 Days') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-rose-700 uppercase tracking-wider">{{ __('90+ Days') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-blue-900 uppercase tracking-wider">{{ __('Total Due') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($vendorBreakdown as $row)
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            <td class="px-4 py-2.5 font-medium text-gray-900 whitespace-nowrap">
                                <a href="{{ route('suppliers.show', $row['supplier_id']) }}" class="text-blue-700 hover:text-blue-900 hover:underline">
                                    {{ $row['supplier_name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">
                                <div>{{ $row['contact_person'] }}</div>
                                @if($row['phone'] !== '-')
                                    <div class="text-gray-400">{{ $row['phone'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-center font-medium text-gray-700">
                                {{ $row['invoice_count'] }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium text-emerald-700 whitespace-nowrap">
                                {{ $row['bucket_0_30'] > 0 ? format_money($row['bucket_0_30']) : '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium text-amber-700 whitespace-nowrap">
                                {{ $row['bucket_31_60'] > 0 ? format_money($row['bucket_31_60']) : '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium text-orange-700 whitespace-nowrap">
                                {{ $row['bucket_61_90'] > 0 ? format_money($row['bucket_61_90']) : '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium text-rose-700 whitespace-nowrap">
                                {{ $row['bucket_90_plus'] > 0 ? format_money($row['bucket_90_plus']) : '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-bold text-blue-950 whitespace-nowrap">
                                {{ format_money($row['total_due']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-gray-500 italic">
                                {{ __('No open vendor payables matching criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($vendorBreakdown) > 0)
                    <tfoot class="bg-gray-100 font-bold border-t-2 border-gray-300">
                        <tr>
                            <td colspan="2" class="px-4 py-2.5 text-gray-900">{{ __('Total Outstanding') }}</td>
                            <td class="px-4 py-2.5 text-center text-gray-900">{{ $kpis['invoices_count'] }}</td>
                            <td class="px-4 py-2.5 text-right text-emerald-800">{{ format_money($kpis['bucket_0_30_due']) }}</td>
                            <td class="px-4 py-2.5 text-right text-amber-800">{{ format_money($kpis['bucket_31_60_due']) }}</td>
                            <td class="px-4 py-2.5 text-right text-orange-800">{{ format_money($kpis['bucket_61_90_due']) }}</td>
                            <td class="px-4 py-2.5 text-right text-rose-800">{{ format_money($kpis['bucket_90_plus_due']) }}</td>
                            <td class="px-4 py-2.5 text-right text-blue-950">{{ format_money($kpis['total_due']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- Detailed Invoices Table -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                <x-heroicon-o-list-bullet class="w-4 h-4 text-primary" />
                <span>{{ __('Detailed Invoices Listing') }}</span>
            </h3>
            <span class="text-xs text-gray-500 font-medium">
                {{ __('Showing') }} {{ $invoices->count() }} {{ __('of') }} {{ $totalCount }} {{ __('invoices') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <!-- Invoice Number -->
                        <th wire:click="sortBy('invoice_number')" class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Invoice #') }}</span>
                                @if($sortField === 'invoice_number')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>

                        <!-- PO Reference -->
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ __('PO Reference') }}
                        </th>

                        <!-- Supplier / Vendor -->
                        <th wire:click="sortBy('supplier_name')" class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Vendor / Supplier') }}</span>
                                @if($sortField === 'supplier_name')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>

                        <!-- Dates -->
                        <th wire:click="sortBy('effective_date')" class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                            <div class="flex items-center gap-1">
                                <span>{{ __('Effective Date') }}</span>
                                @if($sortField === 'effective_date')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>

                        <!-- Aging Days -->
                        <th wire:click="sortBy('age_in_days')" class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                            <div class="flex items-center justify-center gap-1">
                                <span>{{ __('Age (Days)') }}</span>
                                @if($sortField === 'age_in_days')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>

                        <!-- Aging Bracket -->
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ __('Bracket') }}
                        </th>

                        <!-- Status -->
                        <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ __('Status') }}
                        </th>

                        <!-- Total Amount -->
                        <th wire:click="sortBy('amount')" class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Amount') }}</span>
                                @if($sortField === 'amount')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>

                        <!-- Paid Amount -->
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ __('Paid') }}
                        </th>

                        <!-- Net Due -->
                        <th wire:click="sortBy('due_amount')" class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                            <div class="flex items-center justify-end gap-1">
                                <span>{{ __('Due Balance') }}</span>
                                @if($sortField === 'due_amount')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            <!-- Invoice Number Link -->
                            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                                <a
                                    href="{{ route('vendor-invoices.show', $invoice) }}"
                                    class="{{ filled($invoice->invoice_number) ? 'text-blue-700 hover:text-blue-900 hover:underline' : 'italic text-gray-400 hover:text-blue-700 hover:underline' }}"
                                >
                                    {{ $invoice->invoice_number ?: '-' }}
                                </a>
                            </td>

                            <!-- PO Reference Link -->
                            <td class="px-4 py-3 whitespace-nowrap text-xs">
                                <a href="{{ route('purchases.show', $invoice->purchase_id) }}" class="font-medium text-blue-700 hover:text-blue-900 hover:underline">
                                    {{ $invoice->po_reference }}
                                </a>
                            </td>

                            <!-- Vendor / Supplier -->
                            <td class="px-4 py-3 text-gray-900 whitespace-nowrap">
                                <a href="{{ route('suppliers.show', $invoice->supplier_id) }}" class="hover:text-primary hover:underline">
                                    {{ $invoice->supplier?->name ?: '-' }}
                                </a>
                            </td>

                            <!-- Effective Date with tooltip source -->
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                <div>{{ $invoice->effective_date->format('d/m/Y') }}</div>
                                <div class="text-[10px] text-gray-400">
                                    @if($invoice->invoice_date)
                                        {{ __('via Invoice Date') }}
                                    @elseif($invoice->order_received_date)
                                        {{ __('via Received Date') }}
                                    @else
                                        {{ __('via Created At') }}
                                    @endif
                                </div>
                            </td>

                            <!-- Age in Days -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $invoice->age_in_days > 90 ? 'bg-rose-100 text-rose-800' : ($invoice->age_in_days > 60 ? 'bg-orange-100 text-orange-800' : ($invoice->age_in_days > 30 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')) }}">
                                    {{ $invoice->age_in_days }} {{ __('days') }}
                                </span>
                            </td>

                            <!-- Aging Bracket Badge -->
                            <td class="px-4 py-3 text-center whitespace-nowrap text-xs">
                                @php
                                    $badgeStyle = match($invoice->aging_bucket) {
                                        '0-30' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                        '31-60' => 'border-amber-200 bg-amber-50 text-amber-700',
                                        '61-90' => 'border-orange-200 bg-orange-50 text-orange-700',
                                        default => 'border-rose-200 bg-rose-50 text-rose-700',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full border px-2 py-0.5 font-medium {{ $badgeStyle }}">
                                    {{ $invoice->aging_bucket_label }}
                                </span>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <x-status-badge :status="$invoice->status" />
                            </td>

                            <!-- Amount -->
                            <td class="px-4 py-3 text-right text-gray-700 whitespace-nowrap">
                                {{ format_money($invoice->amount) }}
                            </td>

                            <!-- Paid Amount -->
                            <td class="px-4 py-3 text-right text-gray-500 whitespace-nowrap">
                                {{ format_money($invoice->paid_amount) }}
                            </td>

                            <!-- Due Balance -->
                            <td class="px-4 py-3 text-right font-bold text-rose-700 whitespace-nowrap">
                                {{ format_money($invoice->due_amount) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500 italic">
                                {{ __('No open unpaid vendor invoices found matching the current filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="border-t border-gray-200 px-4 py-3 sm:px-6">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
