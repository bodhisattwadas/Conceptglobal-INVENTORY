<x-app-layout title="New Vendor Return">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Create Vendor Return') }}
            </h2>
            <a href="{{ route('vendor-returns.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shadow-xs transition-colors">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                {{ __('Back to Vendor Returns') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="vendorReturnForm({{ json_encode($selectedPurchase ? [
        'id' => $selectedPurchase->id,
        'invoice_number' => $selectedPurchase->invoice_number,
        'supplier_name' => $selectedPurchase->supplier?->name,
        'company_name' => $selectedPurchase->company?->company_name,
        'items' => $selectedPurchase->items->map(fn ($item) => [
            'id' => $item->id,
            'product_name' => $item->product?->name,
            'sku' => $item->product?->sku,
            'batch_number' => $item->batch_number ?: '-',
            'unit_price' => (float) $item->unit_price,
            'available_qty' => (int) ($item->received_quantity ?? 0),
        ])->values(),
    ] : null) }})">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

            @if($errors->any())
                <div class="rounded-md bg-red-50 p-4 border border-red-200">
                    <ul class="list-disc pl-5 text-xs text-red-700 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('vendor-returns.store') }}" method="POST" @submit="handleSubmit($event)">
                @csrf

                <!-- Card 1: PO & Return Details -->
                <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-4">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider border-b border-gray-100 pb-2">
                        1. Select Purchase Order & Return Details
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- PO Selector -->
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700">
                                Purchase Order <span class="text-red-500">*</span>
                            </label>
                            <select
                                name="purchase_id"
                                x-model="purchaseId"
                                @change="onPurchaseChange($event.target.value)"
                                class="block w-full text-xs font-medium rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500"
                                required
                            >
                                <option value="">-- Select Received Purchase Order --</option>
                                @foreach($receivedPurchases as $po)
                                    <option value="{{ $po->id }}" {{ ($selectedPurchase && $selectedPurchase->id == $po->id) ? 'selected' : '' }}>
                                        {{ $po->invoice_number ?: 'PO #'.$po->id }} - {{ $po->supplier?->name }} ({{ $po->purchase_date?->format('d M Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Return Date -->
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700">
                                Return Date <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                name="return_date"
                                value="{{ old('return_date', now()->format('Y-m-d')) }}"
                                class="block w-full text-xs font-medium rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500"
                                required
                            >
                        </div>

                        <!-- Primary Reason -->
                        <div class="space-y-1">
                            <label class="block text-xs font-semibold text-gray-700">
                                Primary Reason <span class="text-red-500">*</span>
                            </label>
                            <select
                                name="reason"
                                x-model="globalReason"
                                class="block w-full text-xs font-medium rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500"
                                required
                            >
                                <option value="damaged">Damaged / Broken in Transit</option>
                                <option value="expired">Expired / Near Expiry</option>
                                <option value="defective">Defective / Quality Issue</option>
                                <option value="wrong_item">Wrong Item Delivered</option>
                                <option value="unsold_return">Unsold / Return Agreement</option>
                                <option value="other">Other (specify in notes)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-1 pt-1">
                        <label class="block text-xs font-semibold text-gray-700">Notes / Remarks</label>
                        <input
                            type="text"
                            name="notes"
                            placeholder="Optional return notes or courier tracking..."
                            value="{{ old('notes') }}"
                            class="block w-full text-xs font-medium rounded-lg border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500"
                        >
                    </div>

                    <!-- Selected PO Header Banner -->
                    <template x-if="selectedPO">
                        <div class="mt-3 p-3 rounded-lg bg-rose-50/60 border border-rose-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div>
                                <span class="text-gray-500">Supplier:</span>
                                <strong class="text-gray-900 ml-1" x-text="selectedPO.supplier_name"></strong>
                                <span class="mx-2 text-gray-300">|</span>
                                <span class="text-gray-500">Brand:</span>
                                <span class="text-gray-700 ml-1" x-text="selectedPO.company_name || '-'"></span>
                            </div>
                            <div class="text-rose-700 font-semibold">
                                <span x-text="selectedPO.items.length + ' batch item(s) in this order'"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Card 2: Items to Return -->
                <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">
                            2. Select Products to Return
                        </h3>
                        <span class="text-xs text-gray-500">Check the items you are returning to the vendor</span>
                    </div>

                    <!-- Empty state if no PO selected -->
                    <div x-show="!selectedPO || !selectedPO.items || selectedPO.items.length === 0" class="py-12 text-center text-gray-400">
                        <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p class="mt-2 text-xs font-medium">Please select a received Purchase Order above to view items available for return.</p>
                    </div>

                    <!-- Items Table -->
                    <div x-show="selectedPO && selectedPO.items && selectedPO.items.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-10 px-3 py-2 text-center">
                                        <span class="sr-only">Select</span>
                                    </th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Product</th>
                                    <th class="px-3 py-2 text-left font-semibold text-gray-600">Batch #</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-600">Purchase Price</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600">Available Stock</th>
                                    <th class="px-3 py-2 text-center font-semibold text-gray-600 w-32">Return Qty</th>
                                    <th class="px-3 py-2 text-right font-semibold text-gray-600">Return Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template x-for="(item, index) in (selectedPO ? selectedPO.items : [])" :key="item.id">
                                    <tr :class="returnItems[item.id]?.selected ? 'bg-rose-50/40' : ''">
                                        <td class="px-3 py-2.5 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="returnItems[item.id]?.selected"
                                                @change="toggleItem(item)"
                                                :disabled="item.available_qty <= 0"
                                                class="rounded border-gray-300 text-rose-600 focus:ring-rose-500 disabled:opacity-40"
                                            >
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="font-bold text-gray-900" x-text="item.product_name"></div>
                                            <div class="text-[10px] text-gray-400 font-mono" x-text="item.sku"></div>
                                        </td>
                                        <td class="px-3 py-2.5 font-mono text-gray-700" x-text="item.batch_number"></td>
                                        <td class="px-3 py-2.5 text-right font-semibold text-gray-700" x-text="formatMoney(item.unit_price)"></td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold"
                                                :class="item.available_qty > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'"
                                                x-text="item.available_qty + ' in stock'"
                                            ></span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                :max="item.available_qty"
                                                x-model.number="returnItems[item.id].qty"
                                                :disabled="!returnItems[item.id]?.selected"
                                                @input="validateQty(item)"
                                                class="w-20 text-center text-xs font-bold rounded border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 py-1 disabled:bg-gray-100 disabled:text-gray-400"
                                            >
                                            <template x-if="returnItems[item.id]?.selected">
                                                <input type="hidden" :name="`items[${index}][purchase_item_id]`" :value="item.id">
                                            </template>
                                            <template x-if="returnItems[item.id]?.selected">
                                                <input type="hidden" :name="`items[${index}][quantity]`" :value="returnItems[item.id].qty">
                                            </template>
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-bold text-rose-700">
                                            <span x-text="returnItems[item.id]?.selected ? formatMoney(returnItems[item.id].qty * item.unit_price) : '-'"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary Box -->
                    <div x-show="selectedCount > 0" class="border-t border-gray-200 pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="text-xs text-gray-600">
                            Returning <strong class="text-gray-900" x-text="selectedCount"></strong> product(s) |
                            Total Units: <strong class="text-gray-900" x-text="totalQty"></strong>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <span class="text-xs text-gray-500 block">Total Debit Note Amount</span>
                                <span class="text-lg font-black text-rose-700" x-text="formatMoney(totalAmount)"></span>
                            </div>
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition-all focus:ring-2 focus:ring-offset-2 focus:ring-rose-500"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Confirm Vendor Return</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function vendorReturnForm(initialPO) {
            return {
                purchaseId: initialPO ? initialPO.id : '',
                selectedPO: initialPO,
                globalReason: 'damaged',
                returnItems: {},

                init() {
                    if (this.selectedPO && this.selectedPO.items) {
                        this.initItemsState(this.selectedPO.items);
                    }
                },

                initItemsState(items) {
                    this.returnItems = {};
                    items.forEach(item => {
                        this.returnItems[item.id] = {
                            selected: false,
                            qty: Math.min(1, item.available_qty),
                        };
                    });
                },

                onPurchaseChange(id) {
                    if (!id) {
                        this.selectedPO = null;
                        this.returnItems = {};
                        return;
                    }
                    window.location.href = `{{ route('vendor-returns.create') }}?purchase_id=${id}`;
                },

                toggleItem(item) {
                    if (!this.returnItems[item.id]) {
                        this.returnItems[item.id] = { selected: true, qty: 1 };
                    } else {
                        this.returnItems[item.id].selected = !this.returnItems[item.id].selected;
                        if (this.returnItems[item.id].selected && (!this.returnItems[item.id].qty || this.returnItems[item.id].qty <= 0)) {
                            this.returnItems[item.id].qty = 1;
                        }
                    }
                },

                validateQty(item) {
                    const state = this.returnItems[item.id];
                    if (!state) return;
                    if (state.qty > item.available_qty) {
                        state.qty = item.available_qty;
                    }
                    if (state.qty < 1) {
                        state.qty = 1;
                    }
                },

                get selectedCount() {
                    return Object.values(this.returnItems).filter(i => i.selected).length;
                },

                get totalQty() {
                    return Object.values(this.returnItems)
                        .filter(i => i.selected)
                        .reduce((sum, i) => sum + (parseInt(i.qty) || 0), 0);
                },

                get totalAmount() {
                    if (!this.selectedPO || !this.selectedPO.items) return 0;
                    return this.selectedPO.items.reduce((sum, item) => {
                        const state = this.returnItems[item.id];
                        if (state && state.selected) {
                            return sum + ((parseInt(state.qty) || 0) * (parseFloat(item.unit_price) || 0));
                        }
                        return sum;
                    }, 0);
                },

                formatMoney(amount) {
                    return '₹' + parseFloat(amount || 0).toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                },

                handleSubmit(e) {
                    if (this.selectedCount === 0) {
                        e.preventDefault();
                        alert('Please select at least one item to return.');
                        return;
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
