@php
    $availableCoupons = $availableCoupons ?? collect();
@endphp
<x-app-layout title="POS">
    <div class="mx-auto sm:px-6 lg:px-8 py-4 space-y-4 max-w-7xl"
         x-data="pos()"
         x-init="init()"
         @keydown.window.f1.prevent="focusProductSelect()"
         @keydown.window.f2.prevent="focusPhoneInput()"
         @keydown.window.f3.prevent="openConfirmation()"
    >
        <!-- Top Section: Customer Phone & Details -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-5">
            <div class="flex items-center justify-between mb-3 border-b border-gray-100 pb-2.5">
                <div class="flex items-center gap-2.5">
                    <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-gray-700">Customer Details</h2>
                    <template x-if="customerFound">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            Existing Customer
                        </span>
                    </template>
                    <template x-if="!customerFound && customer.phone && customer.phone.length >= 7">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                            New Customer (Details optional)
                        </span>
                    </template>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Sale Date Picker -->
                    <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 px-2.5 py-1 rounded-lg">
                        <svg class="w-3.5 h-3.5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <label for="pos_sale_date" class="text-xs font-bold text-gray-700 uppercase tracking-wider">Date:</label>
                        <input
                            id="pos_sale_date"
                            type="date"
                            x-model="saleDate"
                            class="py-0.5 px-2 text-xs font-semibold text-gray-800 bg-white rounded border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <button
                        type="button"
                        @click="resetCustomer()"
                        x-show="customer.phone || customer.name || customer.address || customer.email"
                        class="text-xs font-semibold text-gray-500 hover:text-red-600 flex items-center gap-1 transition-colors px-2 py-1 rounded hover:bg-red-50"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Clear Customer
                    </button>
                </div>
            </div>

            <!-- Customer Fields Grid: Phone (primary), Name, Address, Email/Notes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Phone Input (Lookup key) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Phone Number <span class="text-xs text-indigo-600 font-semibold">[F2]</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        </div>
                        <input
                            type="text"
                            x-ref="phoneInput"
                            x-model="customer.phone"
                            @input.debounce.300ms="lookupCustomerByPhone()"
                            @blur="lookupCustomerByPhone()"
                            placeholder="Enter 10-digit phone..."
                            class="block w-full pl-9 pr-8 text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-medium"
                        >
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
                            <template x-if="isLookingUpCustomer">
                                <svg class="animate-spin h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            </template>
                            <template x-if="!isLookingUpCustomer && customerFound">
                                <svg class="h-4 w-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            </template>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">Auto-fills details if phone exists</p>
                </div>

                <!-- Customer Name -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Customer Name <span class="text-xs text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <input
                        type="text"
                        x-ref="nameInput"
                        x-model="customer.name"
                        placeholder="Customer name..."
                        class="block w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p class="text-[11px] text-gray-400 mt-1" x-text="customerFound ? 'Linked to existing record' : 'Saved for new customer'"></p>
                </div>

                <!-- Address -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Address <span class="text-xs text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <input
                        type="text"
                        x-model="customer.address"
                        placeholder="Customer address..."
                        class="block w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p class="text-[11px] text-gray-400 mt-1">Delivery or billing address</p>
                </div>

                <!-- Email / Notes -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                        Email <span class="text-xs text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <input
                        type="email"
                        x-model="customer.email"
                        placeholder="customer@example.com"
                        class="block w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p class="text-[11px] text-gray-400 mt-1">For digital invoice / records</p>
                </div>
            </div>
        </div>

        <!-- Middle Section: Products & Cart Table (Full Width) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-5 flex flex-col space-y-4">
            <!-- Cart Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200">
                <div class="overflow-x-auto" style="min-height: 280px;">
                    <table class="w-full min-w-[950px] table-fixed divide-y divide-gray-200">
                        <colgroup>
                            <col class="w-[32%]">
                            <col class="w-[11%]">
                            <col class="w-[10%]">
                            <col class="w-[7%]">
                            <col class="w-[13%]">
                            <col class="w-[11%]">
                            <col class="w-[11%]">
                            <col class="w-[5%]">
                        </colgroup>
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Price</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Disc %</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Net Total</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="(item, index) in cart" :key="item.key">
                                <tr :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'" class="hover:bg-indigo-50/40 transition-colors">
                                    <td class="px-4 py-3 align-top min-w-[280px]">
                                        <div class="w-full">
                                            <select
                                                :id="`product_search_${item.key}`"
                                                x-init="initRowProductSelect($el, item)"
                                                autocomplete="off"
                                            ></select>
                                        </div>
                                        <div x-show="item.sku" class="mt-1 flex items-center gap-2">
                                            <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium font-mono text-gray-700 border border-gray-200" x-text="item.sku"></span>
                                            <span class="text-xs text-gray-500 font-medium" x-show="item.max_stock !== undefined && item.max_stock !== null" x-text="'Stock: ' + item.max_stock + (item.unit ? ' ' + item.unit : '')"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600 align-top pt-4">
                                        <span x-show="item.id" x-text="formatCurrency(item.price)"></span>
                                        <span x-show="!item.id" class="text-gray-300">—</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center align-top pt-3">
                                        <template x-if="item.id">
                                            <div>
                                                <div class="flex items-center justify-center">
                                                    <input
                                                        type="number"
                                                        x-model.number="item.quantity"
                                                        min="1"
                                                        :max="item.max_stock"
                                                        @input="validateQty(index)"
                                                        class="w-20 text-center border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm font-semibold"
                                                        placeholder="1"
                                                    >
                                                </div>
                                                <div x-show="item.quantity > item.max_stock" class="text-xs text-red-600 mt-1">
                                                    Max: <span x-text="item.max_stock"></span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!item.id">
                                            <span class="text-gray-300 pt-1 block">—</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-500 align-top pt-4">
                                        <span x-show="item.id" x-text="item.unit || '—'"></span>
                                        <span x-show="!item.id" class="text-gray-300">—</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-semibold text-gray-900 align-top pt-4">
                                        <span x-show="item.id" x-text="formatCurrency(lineGrossTotal(item))"></span>
                                        <span x-show="!item.id" class="text-gray-300">—</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right align-top pt-3">
                                        <template x-if="item.id">
                                            <div class="relative rounded-md shadow-sm w-full min-w-[90px] ml-auto">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    :value="item.discountPercent"
                                                    @input="setItemDiscountPercent(index, $event.target.value)"
                                                    @blur="item.discountPercent = clampDiscountPercent(item.discountPercent)"
                                                    aria-label="Item discount percentage"
                                                    class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-7 text-right sm:text-sm border-gray-300 rounded-md"
                                                    placeholder="0"
                                                >
                                                <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                                                    <span class="text-gray-500 sm:text-xs font-semibold">%</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!item.id">
                                            <span class="text-gray-300 pt-1 block">—</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold text-blue-700 align-top pt-4">
                                        <span x-show="item.id" x-text="formatCurrency(lineNetTotal(item))"></span>
                                        <span x-show="!item.id" class="text-gray-300">—</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium align-top pt-3">
                                        <button
                                            type="button"
                                            @click="removeItem(index)"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 focus:outline-none transition-colors mx-auto"
                                            title="{{ __('Remove product') }}"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr>
                                <td colspan="8" class="px-4 py-3 bg-white border-t border-gray-200">
                                    <button
                                        type="button"
                                        @click="addRow()"
                                        :disabled="hasBlankRow"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-semibold transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                                        :class="hasBlankRow
                                            ? 'bg-gray-200 text-gray-400 cursor-not-allowed border border-gray-200 shadow-none'
                                            : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm focus-visible:outline-emerald-600'"
                                        :title="hasBlankRow ? 'Select a product in the blank row first' : 'Add another product row'"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        <span>{{ __('Add Product') }}</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bottom Section: Compact Two-Column Payment & Totals -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3.5 sm:p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5">

                <!-- Left Column: Payment Method (UPI / Cash / Card) & Tender Details -->
                <div class="flex flex-col justify-between space-y-3">
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">Payment Method</span>
                            <span class="text-[11px] text-gray-400 capitalize" x-text="'Selected: ' + payment.method"></span>
                        </div>

                        <!-- 3 Payment Buttons: UPI / Cash / Card -->
                        <div class="grid grid-cols-3 gap-2" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                            <!-- UPI -->
                            <button
                                type="button"
                                @click="selectPaymentMethod('upi')"
                                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border-2 transition-all font-bold text-xs"
                                :class="payment.method === 'upi' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            >
                                <svg class="w-4 h-4 text-indigo-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                <span>UPI</span>
                            </button>

                            <!-- Cash -->
                            <button
                                type="button"
                                @click="selectPaymentMethod('cash')"
                                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border-2 transition-all font-bold text-xs"
                                :class="payment.method === 'cash' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            >
                                <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                <span>Cash</span>
                            </button>

                            <!-- Card -->
                            <button
                                type="button"
                                @click="selectPaymentMethod('card')"
                                class="flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg border-2 transition-all font-bold text-xs"
                                :class="payment.method === 'card' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                            >
                                <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                <span>Card</span>
                            </button>
                        </div>

                        <!-- Tender Details Box -->
                        <!-- If CASH -->
                        <template x-if="payment.method === 'cash'">
                            <div class="bg-gray-50/90 rounded-lg p-2.5 border border-gray-200 space-y-2">
                                <div class="flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <div class="absolute inset-y-0 flex items-center pointer-events-none" :class="window.currencyPosition === 'left' ? 'left-0 pl-2.5' : 'right-0 pr-2.5'">
                                            <span class="text-gray-500 font-bold text-xs" x-text="window.currencySymbol"></span>
                                        </div>
                                        <input
                                            type="text"
                                            :value="formatNumber(payment.cash_received)"
                                            @input="onCashReceivedInput(unformatNumber($event.target.value))"
                                            class="block w-full py-1.5 text-sm font-bold text-gray-900 border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                                            :class="window.currencyPosition === 'left' ? 'pl-8 pr-2 text-left' : 'pr-8 pl-2 text-right'"
                                            placeholder="Received"
                                        >
                                    </div>
                                    <!-- Change / Due pill -->
                                    <div class="px-2.5 py-1 rounded-md border text-xs font-bold flex items-center gap-1.5 shrink-0"
                                         :class="change < 0 ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700'">
                                        <span class="uppercase text-[10px]" x-text="change < 0 ? 'Due:' : 'Change:'"></span>
                                        <span x-text="formatCurrency(Math.abs(change))"></span>
                                    </div>
                                </div>

                                <!-- Quick buttons -->
                                <div class="grid grid-cols-7 gap-1" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                                    <button
                                        type="button"
                                        @click="selectExactCash()"
                                        class="py-1 rounded text-[11px] font-bold transition-all border shadow-sm"
                                        :class="isExactCash ? 'bg-indigo-600 text-white border-indigo-600 ring-1 ring-indigo-600 font-black' : 'bg-white hover:bg-indigo-50 hover:text-indigo-600 border-gray-300 text-gray-700'"
                                    >
                                        EXACT
                                    </button>
                                    <button type="button" @click="addCash(50)" class="py-1 bg-white hover:bg-gray-100 border border-gray-300 rounded text-[11px] font-bold text-gray-700 transition-colors">
                                        +50
                                    </button>
                                    <button type="button" @click="addCash(100)" class="py-1 bg-white hover:bg-gray-100 border border-gray-300 rounded text-[11px] font-bold text-gray-700 transition-colors">
                                        +100
                                    </button>
                                    <button type="button" @click="addCash(200)" class="py-1 bg-white hover:bg-gray-100 border border-gray-300 rounded text-[11px] font-bold text-gray-700 transition-colors">
                                        +200
                                    </button>
                                    <button type="button" @click="addCash(500)" class="py-1 bg-white hover:bg-gray-100 border border-gray-300 rounded text-[11px] font-bold text-gray-700 transition-colors">
                                        +500
                                    </button>
                                    <button type="button" @click="addCash(1000)" class="py-1 bg-white hover:bg-gray-100 border border-gray-300 rounded text-[11px] font-bold text-gray-700 transition-colors">
                                        +1K
                                    </button>
                                    <button type="button" @click="addCash(2000)" class="py-1 bg-white hover:bg-gray-100 border border-gray-300 rounded text-[11px] font-bold text-gray-700 transition-colors">
                                        +2K
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- If UPI -->
                        <template x-if="payment.method === 'upi'">
                            <div class="bg-indigo-50/60 rounded-lg p-2.5 border border-indigo-100 space-y-1.5">
                                <div class="flex items-center gap-1.5 text-indigo-700 text-xs font-semibold">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Collect via QR Code / UPI App</span>
                                </div>
                                <input
                                    type="text"
                                    x-model="payment.ref"
                                    placeholder="UPI UTR / Reference ID (Optional)..."
                                    class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                                >
                            </div>
                        </template>

                        <!-- If CARD -->
                        <template x-if="payment.method === 'card'">
                            <div class="bg-blue-50/60 rounded-lg p-2.5 border border-blue-100 space-y-1.5">
                                <div class="flex items-center gap-1.5 text-blue-700 text-xs font-semibold">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                    <span>Swipe / Tap on POS machine</span>
                                </div>
                                <input
                                    type="text"
                                    x-model="payment.ref"
                                    placeholder="Card 4 Digits / Auth Ref (Optional)..."
                                    class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                                >
                            </div>
                        </template>
                    </div>

                    <!-- Compact Notes Input -->
                    <div>
                        <input
                            type="text"
                            x-model="payment.notes"
                            placeholder="Transaction notes / remarks (optional)..."
                            class="block w-full text-xs border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 py-1.5"
                        >
                    </div>
                </div>

                <!-- Right Column: Totals Summary & Action Buttons -->
                <div class="flex flex-col justify-between space-y-2.5 bg-gray-50/80 p-3 sm:p-3.5 rounded-xl border border-gray-200">
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between items-center text-gray-600 font-medium">
                            <span>Subtotal</span>
                            <span class="font-semibold text-gray-900" x-text="formatCurrency(subtotal)"></span>
                        </div>

                        <div class="flex justify-between items-center text-red-600" x-show="totalDiscount > 0">
                            <span>Item Discounts</span>
                            <span class="font-semibold" x-text="'- ' + formatCurrency(totalDiscount)"></span>
                        </div>

                        <!-- Coupon Section -->
                        <!-- If Coupon Applied -->
                        <template x-if="appliedCoupon">
                            <div class="py-1 px-2 rounded-lg bg-emerald-50 border border-emerald-200 text-xs flex items-center justify-between">
                                <div class="flex items-center gap-1.5 overflow-hidden">
                                    <div class="flex items-center justify-center w-4 h-4 rounded bg-emerald-200/80 text-emerald-800 shrink-0 text-[10px] font-bold">
                                        ✓
                                    </div>
                                    <span class="font-mono font-bold text-emerald-900 uppercase truncate" x-text="appliedCoupon.code"></span>
                                    <span class="text-[10px] font-semibold px-1 py-0.2 rounded shrink-0"
                                          :class="appliedCoupon.type === 'percentage' ? 'bg-emerald-200/70 text-emerald-800' : 'bg-blue-100 text-blue-800'"
                                          x-text="appliedCoupon.type === 'percentage' ? (appliedCoupon.value + '%') : 'Flat'"></span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="font-bold text-emerald-700" x-text="'- ' + formatCurrency(appliedCoupon.discount_amount)"></span>
                                    <button
                                        type="button"
                                        @click="removeCoupon()"
                                        class="text-gray-400 hover:text-red-600 p-0.5 rounded transition-colors"
                                        title="Remove coupon"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- If No Coupon: Available Coupons Dropdown & Manual Entry -->
                        <div x-show="!appliedCoupon" class="pt-0.5">
                            <!-- Dropdown View -->
                            <div x-show="!manualCouponMode" class="flex items-center gap-1.5">
                                <div class="relative flex-1 min-w-0">
                                    <select
                                        x-model="selectedCouponCode"
                                        @change="handleCouponSelection($event.target.value)"
                                        :disabled="isApplyingCoupon"
                                        class="block w-full py-1 pl-2 pr-7 text-xs font-semibold border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700 shadow-sm truncate disabled:opacity-50"
                                    >
                                        <option value="">🎟️ Select Coupon ({{ count($availableCoupons) }} available)...</option>
                                        @forelse($availableCoupons as $coupon)
                                            <option value="{{ $coupon->code }}">
                                                {{ $coupon->formatted_label }}
                                            </option>
                                        @empty
                                            <option value="" disabled>No active coupons found</option>
                                        @endforelse
                                        <option value="__manual__">✍️ Enter promo code manually...</option>
                                    </select>
                                    <template x-if="isApplyingCoupon">
                                        <div class="absolute inset-y-0 right-7 flex items-center pointer-events-none">
                                            <svg class="animate-spin h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        </div>
                                    </template>
                                </div>
                                <button
                                    type="button"
                                    @click="toggleManualCouponMode(true)"
                                    class="p-1.5 text-gray-500 hover:text-indigo-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors shrink-0 shadow-sm"
                                    title="Type promo code manually"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </button>
                            </div>

                            <!-- Manual Promo Code Input View -->
                            <div x-show="manualCouponMode" class="flex items-center gap-1.5">
                                <div class="relative flex-1 min-w-0">
                                    <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-gray-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                    </div>
                                    <input
                                        type="text"
                                        x-ref="manualCouponInput"
                                        x-model="couponCode"
                                        @keydown.enter.prevent="applyCoupon(couponCode)"
                                        placeholder="PROMO CODE..."
                                        class="block w-full pl-6 pr-2 py-0.5 text-xs uppercase font-semibold border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 bg-white shadow-sm"
                                        :disabled="isApplyingCoupon"
                                    >
                                </div>
                                <button
                                    type="button"
                                    @click="applyCoupon(couponCode)"
                                    :disabled="isApplyingCoupon || !couponCode.trim()"
                                    class="px-2 py-0.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md transition-colors flex items-center gap-1 shrink-0 shadow-sm"
                                >
                                    <template x-if="isApplyingCoupon">
                                        <svg class="animate-spin h-3 w-3 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    </template>
                                    <span>Apply</span>
                                </button>
                                <button
                                    type="button"
                                    @click="toggleManualCouponMode(false)"
                                    class="p-1 text-gray-400 hover:text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors shrink-0 shadow-sm"
                                    title="Back to dropdown"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Other Discount Box at Final Bill -->
                        <div class="flex justify-between items-center py-1">
                            <span class="font-medium text-gray-600">Other Discount</span>
                            <div class="relative w-36 sm:w-44">
                                <div class="absolute inset-y-0 flex items-center pointer-events-none" :class="window.currencyPosition === 'left' ? 'left-0 pl-2.5' : 'right-0 pr-2.5'">
                                    <span class="text-gray-400 font-bold text-xs" x-text="window.currencySymbol"></span>
                                </div>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    :value="billDiscount"
                                    @input="onBillDiscountInput($event.target.value)"
                                    @keydown="filterDiscountKeydown($event)"
                                    class="focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm font-bold text-red-600 bg-white border border-gray-300 rounded-lg py-1.5 shadow-sm transition-colors"
                                    :class="window.currencyPosition === 'left' ? 'pl-8 pr-2.5 text-right' : 'pr-8 pl-2.5 text-left'"
                                    placeholder="0.00"
                                    aria-label="Other discount"
                                >
                            </div>
                        </div>

                        <!-- TOTAL -->
                        <div class="flex justify-between items-baseline pt-2 border-t border-gray-200">
                            <span class="text-xs font-bold text-gray-800 uppercase tracking-wider">TOTAL</span>
                            <span class="text-2xl font-black text-blue-600" x-text="formatCurrency(total)"></span>
                        </div>
                    </div>

                    <!-- Bottom row: Status switcher + Action buttons -->
                    <div class="space-y-2 pt-1 border-t border-gray-200">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">Status:</span>
                            <div class="grid grid-cols-2 gap-1 w-44" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                                <button
                                    type="button"
                                    @click="saleStatus = 'completed'"
                                    class="py-1 px-1.5 text-[11px] font-bold rounded border transition-colors flex items-center justify-center gap-1"
                                    :class="saleStatus === 'completed' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                >
                                    <svg x-show="saleStatus === 'completed'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Completed
                                </button>
                                <button
                                    type="button"
                                    @click="saleStatus = 'pending'"
                                    class="py-1 px-1.5 text-[11px] font-bold rounded border transition-colors flex items-center justify-center gap-1"
                                    :class="saleStatus === 'pending' ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                >
                                    <svg x-show="saleStatus === 'pending'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Pending
                                </button>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="$dispatch('open-modal', { name: 'cancel-modal' })"
                                class="w-1/3 py-2 text-xs font-bold text-red-600 hover:text-white bg-white border border-red-200 hover:bg-red-600 rounded-lg flex items-center justify-center transition-colors shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                CANCEL
                            </button>

                            <button
                                type="button"
                                @click="openConfirmation()"
                                :disabled="isSubmitting || validCartItems.length === 0 || (saleStatus === 'completed' && payment.method === 'cash' && payment.cash_received < total)"
                                class="w-2/3 flex justify-center items-center py-2 px-3 rounded-lg shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                            >
                                <template x-if="isSubmitting">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </template>
                                <span x-text="isSubmitting ? 'Processing...' : 'PAY ' + formatCurrency(total) + ' (F3)'"></span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Alpine Component Logic -->
        <script>
            function pos() {
                return {
                    saleDate: '{{ now()->format('Y-m-d') }}',
                    cart: [],
                    customer: {
                        id: null,
                        phone: '',
                        name: '',
                        address: '',
                        email: '',
                        notes: ''
                    },
                    customerFound: false,
                    isLookingUpCustomer: false,

                    payment: {
                        method: 'cash', // 'upi', 'cash', 'card'
                        cash_received: 0,
                        notes: '',
                        ref: ''
                    },
                    cashIsExact: true,
                    billDiscount: '',
                    couponCode: '',
                    selectedCouponCode: '',
                    manualCouponMode: false,
                    appliedCoupon: null,
                    isApplyingCoupon: false,
                    saleStatus: 'completed',
                    isSubmitting: false,

                    init() {
                        // Load saved data from LocalStorage
                        const savedCart = localStorage.getItem('pos_cart');
                        if (savedCart) {
                            try {
                                const parsed = JSON.parse(savedCart);
                                const mapped = (Array.isArray(parsed) ? parsed : []).map((item) => this.prepareCartItem(item));
                                this.cart = mapped.length ? mapped : [this.blankItem()];
                            } catch (e) {
                                this.cart = [this.blankItem()];
                            }
                        } else {
                            this.cart = [this.blankItem()];
                        }

                        const savedCustomer = localStorage.getItem('pos_customer');
                        if (savedCustomer) {
                            try {
                                const parsed = JSON.parse(savedCustomer);
                                this.customer = {
                                    id: parsed.id || null,
                                    phone: parsed.phone || '',
                                    name: parsed.name || '',
                                    address: parsed.address || '',
                                    email: parsed.email || '',
                                    notes: parsed.notes || ''
                                };
                                this.customerFound = !!parsed.id;
                            } catch (e) {}
                        }

                        const savedPayment = localStorage.getItem('pos_payment');
                        if (savedPayment) {
                            try {
                                this.payment = JSON.parse(savedPayment);
                            } catch (e) {}
                        }

                        const savedBillDiscount = localStorage.getItem('pos_billDiscount') ?? localStorage.getItem('pos_globalDiscount');
                        if (savedBillDiscount !== null && savedBillDiscount !== '') {
                            this.billDiscount = savedBillDiscount;
                        }

                        const savedAppliedCoupon = localStorage.getItem('pos_applied_coupon');
                        if (savedAppliedCoupon) {
                            try {
                                this.appliedCoupon = JSON.parse(savedAppliedCoupon);
                            } catch (e) {
                                this.appliedCoupon = null;
                            }
                        }

                        if (this.payment.method === 'cash') {
                            this.selectExactCash();
                        }

                        // Watchers to synchronize with LocalStorage
                        this.$watch('cart', () => {
                            this.persistCart();
                        });
                        this.$watch('total', (newTotal) => {
                            if (this.payment.method === 'cash' && this.cashIsExact) {
                                this.payment.cash_received = newTotal;
                            }
                        });
                        this.$watch('customer', (val) => localStorage.setItem('pos_customer', JSON.stringify(val)));
                        this.$watch('payment', (val) => localStorage.setItem('pos_payment', JSON.stringify(val)));
                        this.$watch('billDiscount', (val) => {
                            if (val !== '' && val !== null && val !== undefined) {
                                localStorage.setItem('pos_billDiscount', String(val));
                            } else {
                                localStorage.removeItem('pos_billDiscount');
                            }
                            this.syncCashReceived();
                        });
                        this.$watch('appliedCoupon', (val) => {
                            if (val) {
                                localStorage.setItem('pos_applied_coupon', JSON.stringify(val));
                            } else {
                                localStorage.removeItem('pos_applied_coupon');
                            }
                            this.clampBillDiscount();
                            this.syncCashReceived();
                        });
                    },

                    waitForTomSelect(callback) {
                        if (window.TomSelect) callback();
                        else setTimeout(() => this.waitForTomSelect(callback), 50);
                    },

                    blankItem() {
                        return {
                            key: Math.random().toString(36).slice(2),
                            id: null,
                            name: '',
                            sku: '',
                            price: 0,
                            quantity: 1,
                            max_stock: 0,
                            unit: '',
                            discountPercent: 0
                        };
                    },

                    addRow() {
                        const existingBlank = this.cart.find(item => !item.id);
                        if (existingBlank) {
                            this.focusRowSelect(existingBlank);
                            this.$dispatch('toast', { message: 'Please select a product for the existing blank row first.', type: 'warning' });
                            return;
                        }
                        const newItem = this.blankItem();
                        this.cart.push(newItem);
                        this.focusRowSelect(newItem);
                    },

                    removeItem(index) {
                        const item = this.cart[index];
                        this.destroyRowSelect(item);
                        this.cart.splice(index, 1);
                        if (this.cart.length === 0) {
                            const newItem = this.blankItem();
                            this.cart.push(newItem);
                            this.focusRowSelect(newItem);
                        }
                        this.persistCart();
                        this.syncAllProductDropdowns();
                        if (item && item.name) {
                            this.$dispatch('toast', { message: 'Removed "' + item.name + '" from cart.', type: 'info' });
                        }
                    },

                    removeFromCart(index) {
                        this.removeItem(index);
                    },

                    destroyRowSelect(item) {
                        if (!item) return;
                        const selectEl = document.getElementById(`product_search_${item.key}`);
                        if (selectEl?.tomselect) {
                            selectEl.tomselect.destroy();
                        }
                    },

                    focusRowSelect(item) {
                        this.$nextTick(() => {
                            const select = document.getElementById(`product_search_${item.key}`)?.tomselect;
                            if (select) {
                                select.focus();
                            } else {
                                setTimeout(() => {
                                    document.getElementById(`product_search_${item.key}`)?.tomselect?.focus();
                                }, 80);
                            }
                        });
                    },

                    focusProductSelect() {
                        const emptyRow = this.cart.find(item => !item.id);
                        if (emptyRow) {
                            this.focusRowSelect(emptyRow);
                        } else {
                            this.addRow();
                        }
                    },

                    selectedProductIds(exceptItem = null) {
                        const exceptKey = exceptItem?.key;
                        return this.cart
                            .filter(row => row.key !== exceptKey)
                            .map(row => String(row.id || ''))
                            .filter(Boolean);
                    },

                    filterAvailableProducts(products, item) {
                        const selectedIds = this.selectedProductIds(item);
                        return (Array.isArray(products) ? products : []).filter(product => {
                            const id = String(product.value ?? product.id ?? '');
                            return id && !selectedIds.includes(id);
                        });
                    },

                    syncAllProductDropdowns(exceptItem = null) {
                        const exceptKey = exceptItem?.key;
                        this.cart.forEach(row => {
                            if (row.key === exceptKey) return;

                            const select = document.getElementById(`product_search_${row.key}`)?.tomselect;
                            if (!select) return;

                            const forbiddenIds = this.selectedProductIds(row);
                            let changed = false;

                            forbiddenIds.forEach(id => {
                                if (select.options[id] && String(select.getValue()) !== String(id)) {
                                    select.removeOption(id);
                                    changed = true;
                                }
                            });

                            if (changed) {
                                select.refreshOptions(false);
                            }

                            if (typeof select.clearCache === 'function') {
                                select.clearCache();
                            }
                            select.loadedSearches = {};
                        });
                    },

                    setRowProduct(item, product) {
                        item.id = product.id || product.value;
                        item.name = product.name || product.text;
                        item.sku = product.sku || '';
                        item.product_code = product.product_code || '';
                        item.price = parseFloat(product.selling_price || product.price) || 0;
                        item.quantity = item.quantity && item.quantity > 0 ? item.quantity : 1;
                        item.max_stock = product.quantity !== undefined ? product.quantity : 9999;
                        item.unit = product.unit ? (typeof product.unit === 'object' ? (product.unit.symbol || product.unit.name || '') : product.unit) : '';
                        item.discountPercent = item.discountPercent || 0;

                        if (item.quantity > item.max_stock) {
                            item.quantity = item.max_stock;
                        }

                        this.persistCart();
                        this.syncAllProductDropdowns(item);
                        this.$dispatch('toast', { message: 'Selected "' + item.name + '"', type: 'success' });
                    },

                    clearRowProduct(item) {
                        item.id = null;
                        item.name = '';
                        item.sku = '';
                        item.price = 0;
                        item.quantity = 1;
                        item.max_stock = 0;
                        item.unit = '';
                        item.discountPercent = 0;
                        this.persistCart();
                        this.syncAllProductDropdowns(item);
                    },

                    handleProductScan(select, item, query) {
                        const qLower = query.trim().toLowerCase();
                        if (!qLower) return;

                        const existingRow = this.cart.find(row => 
                            row.key !== item.key && row.id && (
                                (row.sku && row.sku.toLowerCase() === qLower) ||
                                (row.name && row.name.toLowerCase() === qLower)
                            )
                        );

                        if (existingRow) {
                            if (existingRow.quantity < existingRow.max_stock) {
                                existingRow.quantity += 1;
                                this.persistCart();
                                this.$dispatch('toast', { message: `Increased quantity for "${existingRow.name}" to ${existingRow.quantity}`, type: 'info' });
                            } else {
                                this.$dispatch('toast', { message: `Maximum stock (${existingRow.max_stock}) reached for "${existingRow.name}"`, type: 'warning' });
                            }
                            select.clear(true);
                            select.close();
                            if (select.dropdown_input) select.dropdown_input.value = '';
                            if (select.control_input) select.control_input.value = '';
                            this.focusRowSelect(item);
                            return;
                        }

                        const exactMatch = Object.values(select.options).find(opt => 
                            (opt.sku && opt.sku.toLowerCase() === qLower) ||
                            (opt.text && opt.text.toLowerCase() === qLower) ||
                            (opt.name && opt.name.toLowerCase() === qLower)
                        );

                        if (exactMatch) {
                            select.addOption(exactMatch);
                            select.setValue(exactMatch.value || exactMatch.id);
                            this.setRowProduct(item, exactMatch);
                            select.close();
                            return;
                        }

                        fetch('{{ route("ajax.products.search") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ q: query })
                        })
                        .then(r => r.json())
                        .then(products => {
                            const list = Array.isArray(products) ? products : [];
                            const available = this.filterAvailableProducts(list, item);

                            if (available.length === 0) {
                                this.$dispatch('toast', { message: `No available product found matching "${query}"`, type: 'warning' });
                                return;
                            }

                            const match = available.find(p => p.sku && p.sku.toLowerCase() === qLower) ||
                                          available.find(p => p.sku && p.sku.toLowerCase().startsWith(qLower)) ||
                                          available.find(p => (p.text || p.name || '').toLowerCase() === qLower) ||
                                          available[0];

                            select.addOption(match);
                            select.setValue(match.value || match.id);
                            this.setRowProduct(item, match);
                            select.close();
                        })
                        .catch(err => console.error('Scan error:', err));
                    },

                    initRowProductSelect(el, item) {
                        this.waitForTomSelect(() => {
                            if (el.tomselect) return;

                            const select = new TomSelect(el, {
                                placeholder: 'Search product (Barcode, Name, or SKU)...',
                                preload: 'focus',
                                valueField: 'value',
                                labelField: 'text',
                                searchField: ['text', 'name', 'sku', 'product_code'],
                                plugins: ['dropdown_input', 'clear_button'],
                                openOnFocus: true,
                                load: (query, callback) => {
                                    fetch('{{ route("ajax.products.search") }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({ q: query })
                                    })
                                    .then(r => r.json())
                                    .then(products => callback(this.filterAvailableProducts(products, item)))
                                    .catch(() => callback([]));
                                },
                                closeAfterSelect: true,
                                onItemAdd: (value) => {
                                    const forbiddenIds = this.selectedProductIds(item);
                                    if (forbiddenIds.includes(String(value))) {
                                        select.removeItem(value, true);
                                        select.removeOption(value);
                                        select.refreshOptions(false);
                                        this.$dispatch('toast', { message: 'Product already added in another row.', type: 'warning' });
                                        return;
                                    }
                                    const data = select.options[value];
                                    if (data) {
                                        this.setRowProduct(item, data);
                                    }
                                },
                                onChange: (value) => {
                                    if (!value) {
                                        this.clearRowProduct(item);
                                    }
                                },
                                render: {
                                    option: (data, escape) => {
                                        const name = escape(data.text || data.name || '');
                                        const sku = escape(data.sku || '');
                                        const productCode = escape(data.product_code || '');
                                        const price = this.formatCurrency(data.selling_price || data.price || 0);
                                        const qty = escape(String(data.quantity || 0));
                                        const unit = escape(data.unit?.symbol || '');

                                        const codeParts = [];
                                        if (productCode) {
                                            codeParts.push(`Barcode: ${productCode}`);
                                        }
                                        if (sku) {
                                            codeParts.push(`SKU: ${sku}`);
                                        }
                                        const codeInfo = codeParts.join(' | ');

                                        return `<div class="flex items-center justify-between gap-3 w-full py-2 px-2.5">
                                            <div class="min-w-0 pr-2">
                                                <div class="font-semibold text-gray-900 truncate">${name}</div>
                                                <div class="text-xs text-gray-500 font-mono mt-0.5">${codeInfo}</div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <div class="font-bold text-indigo-600">${price}</div>
                                                <div class="text-xs ${Number(data.quantity) > 0 ? 'text-green-600' : 'text-red-600'} font-semibold">Stock: ${qty} ${unit}</div>
                                            </div>
                                        </div>`;
                                    },
                                    item: (data, escape) => {
                                        return `<div class="truncate font-semibold text-gray-900">${escape(data.text || data.name || '')}</div>`;
                                    },
                                    no_results: (data, escape) => {
                                        return `<div class="no-results px-3 py-2 text-sm text-gray-500">No products found</div>`;
                                    }
                                }
                            });

                            if (select.control_input) {
                                select.control_input.placeholder = 'Search product (Barcode, Name, or SKU)...';
                            }

                            const handleScannerKeyDown = (e) => {
                                if (e.key === 'Enter' || e.keyCode === 13) {
                                    const input = e.target;
                                    const query = (input.value || '').trim();
                                    if (!query) return;

                                    const qLower = query.toLowerCase();

                                    const activeVal = select.activeOption?.dataset?.value;
                                    const activeData = activeVal ? select.options[activeVal] : null;
                                    const activeMatches = activeData && (
                                        (activeData.sku && activeData.sku.toLowerCase() === qLower) ||
                                        (activeData.text && activeData.text.toLowerCase() === qLower) ||
                                        (activeData.name && activeData.name.toLowerCase() === qLower)
                                    );

                                    if (!activeMatches) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        e.stopImmediatePropagation();
                                        this.handleProductScan(select, item, query);
                                    }
                                }
                            };

                            select.control_input?.addEventListener('keydown', handleScannerKeyDown, true);
                            if (select.dropdown_input) {
                                select.dropdown_input.addEventListener('keydown', handleScannerKeyDown, true);
                            }
                            select.wrapper?.addEventListener('keydown', (e) => {
                                if ((e.key === 'Enter' || e.keyCode === 13) && (e.target?.classList.contains('dropdown-input') || e.target?.classList.contains('ts-input'))) {
                                    handleScannerKeyDown(e);
                                }
                            }, true);

                            select.on('dropdown_open', () => {
                                const forbiddenIds = this.selectedProductIds(item);
                                let changed = false;
                                forbiddenIds.forEach(id => {
                                    if (select.options[id] && String(select.getValue()) !== String(id)) {
                                        select.removeOption(id);
                                        changed = true;
                                    }
                                });
                                if (changed) {
                                    select.refreshOptions(false);
                                }
                                if (Object.keys(select.options).length <= (item.id ? 1 : 0)) {
                                    select.load('');
                                }
                            });

                            if (item.id && item.name) {
                                select.addOption({
                                    value: item.id,
                                    id: item.id,
                                    text: item.name,
                                    name: item.name,
                                    sku: item.sku,
                                    selling_price: item.price,
                                    price: item.price,
                                    quantity: item.max_stock,
                                    unit: item.unit ? { symbol: item.unit, name: item.unit } : null
                                });
                                select.setValue(item.id, true);
                            }
                        });
                    },

                    persistCart() {
                        const toSave = (this.cart || []).filter(item => !!item.id);
                        localStorage.setItem('pos_cart', JSON.stringify(toSave));
                        this.recalculateCouponDiscount();
                        this.clampBillDiscount();
                        this.syncCashReceived();
                    },

                    focusPhoneInput() {
                        this.$nextTick(() => {
                            if (this.$refs.phoneInput) {
                                this.$refs.phoneInput.focus();
                                this.$refs.phoneInput.select();
                            }
                        });
                    },

                    // Customer Phone Lookup
                    lookupCustomerByPhone() {
                        const rawPhone = (this.customer.phone || '').trim();
                        if (!rawPhone || rawPhone.length < 5) {
                            this.customerFound = false;
                            this.customer.id = null;
                            return;
                        }

                        this.isLookingUpCustomer = true;
                        fetch('{{ route("ajax.customers.by-phone") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ phone: rawPhone })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.found && data.customer) {
                                this.customer.id = data.customer.id;
                                this.customer.name = data.customer.name || '';
                                this.customer.address = data.customer.address || '';
                                this.customer.email = data.customer.email || '';
                                this.customer.notes = data.customer.notes || '';
                                this.customerFound = true;
                                this.$dispatch('toast', { message: 'Existing customer found: ' + data.customer.name, type: 'success' });
                            } else {
                                this.customer.id = null;
                                this.customerFound = false;
                            }
                        })
                        .catch(err => {
                            console.error('Customer lookup error:', err);
                        })
                        .finally(() => {
                            this.isLookingUpCustomer = false;
                        });
                    },

                    resetCustomer() {
                        this.customer = {
                            id: null,
                            phone: '',
                            name: '',
                            address: '',
                            email: '',
                            notes: ''
                        };
                        this.customerFound = false;
                        this.focusPhoneInput();
                    },

                    clearStorage() {
                        localStorage.removeItem('pos_cart');
                        localStorage.removeItem('pos_customer');
                        localStorage.removeItem('pos_payment');
                        localStorage.removeItem('pos_globalDiscount');
                        localStorage.removeItem('pos_billDiscount');
                        localStorage.removeItem('pos_applied_coupon');
                    },

                    prepareCartItem(item) {
                        const price = parseFloat(item.price) || 0;
                        let discountPercent = item.discountPercent;

                        if (discountPercent === undefined || discountPercent === null || discountPercent === '') {
                            const discount = parseFloat(item.discount) || 0;
                            discountPercent = price > 0 && discount > 0 ? (discount / price) * 100 : 0;
                        }

                        return {
                            ...item,
                            key: item.key || Math.random().toString(36).slice(2),
                            price,
                            quantity: parseInt(item.quantity) || 1,
                            discountPercent: this.clampDiscountPercent(discountPercent),
                        };
                    },

                    validateQty(index) {
                        const item = this.cart[index];
                        if (!item || !item.id) return;
                        if (item.quantity > item.max_stock) {
                            item.quantity = item.max_stock;
                            this.$dispatch('toast', { message: 'Maximum available stock reached', type: 'warning' });
                        }
                        if (item.quantity < 1 || isNaN(item.quantity)) item.quantity = 1;
                        this.persistCart();
                    },

                    setItemDiscountPercent(index, value) {
                        const percent = this.clampDiscountPercent(value);
                        this.cart[index].discountPercent = value !== '' && Number(value) === percent ? value : percent;
                        this.persistCart();
                        this.recalculateCouponDiscount();
                        this.clampBillDiscount();
                    },

                    filterDiscountKeydown(e) {
                        // Allow navigation, editing, and functional keys
                        if ([
                            'Backspace', 'Tab', 'Enter', 'Delete', 'Escape',
                            'ArrowLeft', 'ArrowRight', 'Home', 'End'
                        ].includes(e.key)) {
                            return;
                        }

                        // Allow copy, paste, select-all, cut, undo shortcuts
                        if (e.ctrlKey || e.metaKey) {
                            return;
                        }

                        // Strictly block ArrowUp and ArrowDown ("no number up/down")
                        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                            e.preventDefault();
                            return;
                        }

                        // Allow digits 0-9
                        if (/^[0-9]$/.test(e.key)) {
                            return;
                        }

                        const input = e.target;
                        const val = input.value || '';
                        const selStart = input.selectionStart ?? 0;
                        const selEnd = input.selectionEnd ?? 0;

                        // Allow decimal separator if not already present or if selected text covers it
                        if (e.key === '.' || (window.decimalSeparator && e.key === window.decimalSeparator)) {
                            const hasDot = val.includes('.');
                            const selectedCoversDot = hasDot && val.slice(selStart, selEnd).includes('.');
                            if (!hasDot || selectedCoversDot) {
                                return;
                            }
                        }

                        // Allow minus sign at index 0 if not already present or if selected text covers it
                        if (e.key === '-') {
                            const hasMinus = val.includes('-');
                            const selectedCoversMinus = hasMinus && val.slice(selStart, selEnd).includes('-');
                            if (selStart === 0 && (!hasMinus || selectedCoversMinus)) {
                                return;
                            }
                        }

                        // Block all other keys (letters, symbols, spaces, etc. - "ONLY number in this textbox")
                        e.preventDefault();
                    },

                    onBillDiscountInput(val) {
                        if (val === '' || val === null || val === undefined) {
                            this.billDiscount = '';
                            this.syncCashReceived();
                            return;
                        }

                        let raw = String(val).trim();
                        const isNegative = raw.startsWith('-');

                        // Retain ONLY digits and at most one decimal point
                        let digitsAndDot = raw.replace(/[^0-9.]/g, '');
                        const parts = digitsAndDot.split('.');
                        if (parts.length > 2) {
                            digitsAndDot = parts[0] + '.' + parts.slice(1).join('');
                        }

                        let cleaned = (isNegative ? '-' : '') + digitsAndDot;

                        // Handle intermediate typing states like "-", ".", "-."
                        if (cleaned === '-' || cleaned === '.' || cleaned === '-.') {
                            this.billDiscount = cleaned;
                            this.syncCashReceived();
                            return;
                        }

                        if (cleaned === '') {
                            this.billDiscount = '';
                            this.syncCashReceived();
                            return;
                        }

                        const absVal = Math.abs(parseFloat(cleaned));
                        if (isNaN(absVal)) {
                            this.billDiscount = '';
                            this.syncCashReceived();
                            return;
                        }

                        const max = this.maxBillDiscount;
                        if (absVal > max) {
                            this.billDiscount = isNegative ? ('-' + max) : String(max);
                            this.$dispatch('toast', {
                                message: 'Other discount cannot exceed remaining subtotal (' + this.formatCurrency(max) + ')',
                                type: 'warning'
                            });
                            this.syncCashReceived();
                            return;
                        }

                        this.billDiscount = cleaned;
                        this.syncCashReceived();
                    },

                    clampBillDiscount() {
                        const max = this.maxBillDiscount;
                        if (this.numericBillDiscount > max) {
                            const isNegative = String(this.billDiscount).startsWith('-');
                            this.billDiscount = max > 0 ? (isNegative ? ('-' + max) : String(max)) : '';
                        }
                    },

                    syncCashReceived() {
                        if (this.payment.method === 'cash' && this.cashIsExact) {
                            this.$nextTick(() => {
                                this.payment.cash_received = this.total;
                            });
                        }
                    },

                    handleCouponSelection(code) {
                        if (!code) return;

                        if (code === '__manual__') {
                            this.toggleManualCouponMode(true);
                            this.selectedCouponCode = '';
                            return;
                        }

                        if (this.validCartItems.length === 0) {
                            this.$dispatch('toast', { message: 'Add products to cart before applying coupon.', type: 'warning' });
                            this.selectedCouponCode = '';
                            return;
                        }

                        this.applyCoupon(code);
                    },

                    toggleManualCouponMode(enabled) {
                        this.manualCouponMode = enabled;
                        if (enabled) {
                            this.selectedCouponCode = '';
                            this.$nextTick(() => {
                                if (this.$refs.manualCouponInput) {
                                    this.$refs.manualCouponInput.focus();
                                }
                            });
                        } else {
                            this.couponCode = '';
                            this.selectedCouponCode = '';
                        }
                    },

                    applyCoupon(codeOverride = null) {
                        const code = (codeOverride || this.couponCode || this.selectedCouponCode || '').trim();
                        if (!code) {
                            this.$dispatch('toast', { message: 'Please enter or select a coupon code.', type: 'warning' });
                            return;
                        }
                        if (this.validCartItems.length === 0) {
                            this.$dispatch('toast', { message: 'Add products to cart before applying coupon.', type: 'warning' });
                            this.selectedCouponCode = '';
                            return;
                        }

                        const eligibleSubtotal = Math.max(0, this.subtotal - this.totalDiscount);
                        if (eligibleSubtotal <= 0) {
                            this.$dispatch('toast', { message: 'Cart total after item discounts must be greater than zero to apply a coupon.', type: 'warning' });
                            this.selectedCouponCode = '';
                            return;
                        }

                        this.isApplyingCoupon = true;
                        fetch('{{ route("ajax.coupons.apply") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                code: code,
                                subtotal: eligibleSubtotal
                            })
                        })
                        .then(res => res.json().then(data => ({ ok: res.ok, data })))
                        .then(({ ok, data }) => {
                            if (ok && data.valid && data.coupon) {
                                this.appliedCoupon = {
                                    id: data.coupon.id,
                                    code: data.coupon.code,
                                    type: data.coupon.type,
                                    value: data.coupon.value,
                                    min_purchase_amount: data.coupon.min_purchase_amount,
                                    max_discount_amount: data.coupon.max_discount_amount,
                                    discount_amount: data.discount_amount
                                };
                                this.couponCode = '';
                                this.selectedCouponCode = '';
                                this.manualCouponMode = false;
                                this.clampBillDiscount();
                                this.syncCashReceived();
                                this.$dispatch('toast', { message: data.message || 'Coupon applied successfully!', type: 'success' });
                            } else {
                                this.selectedCouponCode = '';
                                this.$dispatch('toast', { message: data.message || 'Invalid coupon code.', type: 'error' });
                            }
                        })
                        .catch(err => {
                            console.error('Coupon apply error:', err);
                            this.selectedCouponCode = '';
                            this.$dispatch('toast', { message: 'Failed to apply coupon.', type: 'error' });
                        })
                        .finally(() => {
                            this.isApplyingCoupon = false;
                        });
                    },

                    removeCoupon() {
                        this.appliedCoupon = null;
                        this.couponCode = '';
                        this.selectedCouponCode = '';
                        this.clampBillDiscount();
                        this.syncCashReceived();
                        this.$dispatch('toast', { message: 'Coupon removed.', type: 'info' });
                    },

                    recalculateCouponDiscount() {
                        if (!this.appliedCoupon) return;

                        if (this.validCartItems.length === 0) {
                            this.appliedCoupon = null;
                            this.clampBillDiscount();
                            this.syncCashReceived();
                            return;
                        }

                        const eligibleSubtotal = Math.max(0, this.subtotal - this.totalDiscount);

                        if (this.appliedCoupon.min_purchase_amount && eligibleSubtotal < this.appliedCoupon.min_purchase_amount) {
                            const code = this.appliedCoupon.code;
                            this.appliedCoupon = null;
                            this.clampBillDiscount();
                            this.syncCashReceived();
                            this.$dispatch('toast', {
                                message: 'Cart subtotal fell below minimum purchase for coupon ' + code + '. Coupon removed.',
                                type: 'warning'
                            });
                            return;
                        }

                        let discount = 0;
                        if (this.appliedCoupon.type === 'percentage') {
                            let raw = (eligibleSubtotal * parseFloat(this.appliedCoupon.value)) / 100;
                            if (this.appliedCoupon.max_discount_amount) {
                                raw = Math.min(raw, parseFloat(this.appliedCoupon.max_discount_amount));
                            }
                            discount = Math.min(raw, eligibleSubtotal);
                        } else {
                            discount = Math.min(parseFloat(this.appliedCoupon.value), eligibleSubtotal);
                        }

                        discount = Math.round(discount * 100) / 100;
                        this.appliedCoupon.discount_amount = discount;
                        this.clampBillDiscount();
                        this.syncCashReceived();
                    },

                    selectPaymentMethod(method) {
                        this.payment.method = method;
                        if (method === 'cash') {
                            this.selectExactCash();
                        }
                    },

                    selectExactCash() {
                        this.cashIsExact = true;
                        this.payment.cash_received = this.total;
                    },

                    onCashReceivedInput(value) {
                        this.payment.cash_received = value;
                        this.cashIsExact = (this.total > 0 && Math.abs(value - this.total) < 0.001);
                    },

                    addCash(amount) {
                        const current = parseFloat(this.payment.cash_received) || 0;
                        const newAmount = current + amount;
                        this.payment.cash_received = newAmount;
                        this.cashIsExact = (this.total > 0 && Math.abs(newAmount - this.total) < 0.001);
                    },

                    // Computed properties
                    get validCartItems() {
                        return this.cart.filter(item => !!item.id);
                    },

                    get hasBlankRow() {
                        return this.cart.some(item => !item.id);
                    },

                    get subtotal() {
                        return this.cart.reduce((sum, item) => sum + (item.id ? (item.price * item.quantity) : 0), 0);
                    },

                    get totalDiscount() {
                        return this.cart.reduce((sum, item) => sum + (item.id ? (this.lineDiscountAmount(item) * item.quantity) : 0), 0);
                    },

                    get numericBillDiscount() {
                        if (!this.billDiscount) return 0;
                        const val = parseFloat(this.billDiscount);
                        if (isNaN(val) || val === 0) return 0;
                        return Math.abs(val);
                    },

                    get couponDiscount() {
                        return this.appliedCoupon ? (parseFloat(this.appliedCoupon.discount_amount) || 0) : 0;
                    },

                    get maxBillDiscount() {
                        const remaining = Math.max(0, this.subtotal - this.totalDiscount - this.couponDiscount);
                        return Math.round(remaining * 100) / 100;
                    },

                    get globalDiscount() {
                        return Math.round((this.couponDiscount + this.numericBillDiscount) * 100) / 100;
                    },

                    get total() {
                        return Math.max(0, Math.round((this.subtotal - this.totalDiscount - this.globalDiscount) * 100) / 100);
                    },

                    get change() {
                        if (this.payment.method !== 'cash') return 0;
                        return (parseFloat(this.payment.cash_received) || 0) - this.total;
                    },

                    get isExactCash() {
                        if (this.payment.method !== 'cash') return false;
                        if (this.cashIsExact) return true;
                        return this.total > 0 && Math.abs((parseFloat(this.payment.cash_received) || 0) - this.total) < 0.001;
                    },

                    // Helpers
                    clampDiscountPercent(value) {
                        const percent = parseFloat(value) || 0;
                        return Math.min(100, Math.max(0, percent));
                    },

                    lineDiscountAmount(item) {
                        return Math.round((parseFloat(item.price) || 0) * this.clampDiscountPercent(item.discountPercent)) / 100;
                    },

                    lineGrossTotal(item) {
                        return (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0);
                    },

                    lineNetTotal(item) {
                        return Math.max(0, this.lineGrossTotal(item) - (this.lineDiscountAmount(item) * (parseInt(item.quantity) || 0)));
                    },

                    formatCurrency(value) {
                        return window.formatMoney(value);
                    },

                    unformatNumber(value) {
                        if (typeof value !== 'string') return value || 0;

                        let raw = value;
                        if (window.thousandSeparator) {
                            raw = raw.split(window.thousandSeparator).join('');
                        }

                        if (window.decimalSeparator && window.decimalSeparator !== '.') {
                            raw = raw.replace(window.decimalSeparator, '.');
                        }

                        raw = raw.replace(/[^0-9\.-]/g, '');

                        if (raw === '' || raw === '-') return 0;
                        if (raw.endsWith('.')) return raw;

                        return parseFloat(raw) || 0;
                    },

                    formatNumber(value) {
                        if (typeof value === 'string' && value.endsWith('.')) {
                            return value.replace('.', window.decimalSeparator);
                        }

                        let amount = parseFloat(value) || 0;
                        let isNegative = amount < 0;
                        amount = Math.abs(amount);

                        let strAmount = amount.toString();
                        let parts = strAmount.split('.');
                        let integerPart = parts[0];
                        let decimalPart = parts.length > 1 ? window.decimalSeparator + parts[1] : '';

                        let rgx = /(\d+)(\d{3})/;
                        while (rgx.test(integerPart)) {
                            integerPart = integerPart.replace(rgx, '$1' + window.thousandSeparator + '$2');
                        }

                        let num = integerPart + decimalPart;
                        return isNegative ? '-' + num : num;
                    },

                    // Confirmation & Submission
                    openConfirmation() {
                        if (this.validCartItems.length === 0) {
                            this.$dispatch('toast', { message: 'Cart is empty. Please select a product first.', type: 'warning' });
                            return;
                        }
                        if (this.globalDiscount > (this.subtotal - this.totalDiscount)) {
                            this.$dispatch('toast', { message: 'Discount cannot exceed remaining subtotal.', type: 'error' });
                            return;
                        }
                        if (this.saleStatus === 'completed' && this.payment.method === 'cash' && this.payment.cash_received < this.total) {
                            this.$dispatch('toast', { message: 'Cash received is less than total amount.', type: 'error' });
                            return;
                        }

                        this.$dispatch('open-modal', { name: 'confirmation-modal' });
                    },

                    async submitSale() {
                        if (this.saleStatus === 'completed' && this.payment.method === 'cash' && this.payment.cash_received < this.total) {
                            this.$dispatch('toast', { message: 'Insufficient payment!', type: 'error' });
                            return;
                        }

                        this.isSubmitting = true;

                        try {
                            // If customer is new, but customer name is provided, auto-create customer record
                            let customerId = this.customer.id;

                            if (!customerId && this.customer.name && this.customer.name.trim()) {
                                try {
                                    const custRes = await fetch('{{ route("ajax.customers.store") }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'Accept': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({
                                            name: this.customer.name.trim(),
                                            phone: this.customer.phone ? this.customer.phone.trim() : null,
                                            address: this.customer.address ? this.customer.address.trim() : null,
                                            email: this.customer.email ? this.customer.email.trim() : null,
                                            notes: this.customer.notes ? this.customer.notes.trim() : null,
                                        })
                                    });

                                    if (custRes.ok) {
                                        const newCust = await custRes.json();
                                        customerId = newCust.id;
                                        this.customer.id = newCust.id;
                                        this.customerFound = true;
                                    }
                                } catch (custErr) {
                                    console.warn('Failed to auto-create customer:', custErr);
                                }
                            }

                            // Build items payload from valid selected products
                            const items = this.validCartItems.map(item => ({
                                product_id: item.id,
                                quantity: item.quantity,
                                unit_price: item.price,
                                discount: this.lineDiscountAmount(item)
                            }));

                            // Compose notes: append any guest details or payment reference
                            let notes = (this.payment.notes || '').trim();
                            if (this.payment.ref && this.payment.ref.trim()) {
                                notes = (notes ? notes + ' | ' : '') + 'Ref: ' + this.payment.ref.trim();
                            }
                            if (!customerId && (this.customer.phone || this.customer.name || this.customer.address)) {
                                const guestDetails = [
                                    this.customer.name ? 'Name: ' + this.customer.name.trim() : '',
                                    this.customer.phone ? 'Phone: ' + this.customer.phone.trim() : '',
                                    this.customer.address ? 'Address: ' + this.customer.address.trim() : '',
                                ].filter(Boolean).join(', ');

                                if (guestDetails) {
                                    notes = (notes ? notes + '\n' : '') + '[Guest]: ' + guestDetails;
                                }
                            }

                            const payload = {
                                customer_id: customerId,
                                items: items,
                                payment_method: this.payment.method,
                                cash_received: this.payment.method === 'cash' ? this.payment.cash_received : this.total,
                                notes: notes || null,
                                bill_discount: this.numericBillDiscount,
                                global_discount: this.globalDiscount,
                                coupon_id: this.appliedCoupon ? this.appliedCoupon.id : null,
                                coupon_code: this.appliedCoupon ? this.appliedCoupon.code : null,
                                status: this.saleStatus,
                                sale_date: this.saleDate || '{{ now()->format('Y-m-d') }}',
                                _token: '{{ csrf_token() }}'
                            };

                            const res = await fetch('{{ route("sales.store") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify(payload)
                            });

                            const data = await res.json();

                            if (res.ok && data.success) {
                                this.$dispatch('close-modal', { name: 'confirmation-modal' });

                                if (data.print_url) {
                                    window.open(data.print_url, '_blank');
                                }

                                this.clearStorage();
                                this.resetForm();

                                this.$dispatch('toast', { message: 'Transaction Successful!', type: 'success' });
                            } else {
                                this.$dispatch('toast', { message: data.message || 'Error occurred while saving sale.', type: 'error' });
                            }

                        } catch (e) {
                            console.error(e);
                            this.$dispatch('toast', { message: 'Network error occurred', type: 'error' });
                        } finally {
                            this.isSubmitting = false;
                        }
                    },

                    resetForm() {
                        this.cart.forEach(item => this.destroyRowSelect(item));
                        this.cart = [this.blankItem()];
                        this.resetCustomer();
                        this.saleDate = '{{ now()->format('Y-m-d') }}';
                        this.payment = {
                            method: 'cash',
                            cash_received: 0,
                            notes: '',
                            ref: ''
                        };
                        this.billDiscount = '';
                        this.appliedCoupon = null;
                        this.couponCode = '';
                        this.selectedCouponCode = '';
                        this.manualCouponMode = false;
                        this.cashIsExact = true;
                        this.clearStorage();
                    }
                }
            }
        </script>

        <!-- Payment Confirmation Modal -->
        <x-modal name="confirmation-modal" focusable>
            <div class="p-6">
                <!-- Header -->
                <div class="mb-5 space-y-1 text-center sm:text-left border-b border-gray-200 pb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        Confirm Payment & Sale
                    </h3>
                    <p class="text-xs text-gray-500">
                        Please review the details below before completing transaction.
                    </p>
                </div>

                <!-- Summary Details -->
                <div class="space-y-3 py-2 text-sm">
                    <!-- Customer -->
                    <div class="flex items-center justify-between py-1 border-b border-gray-100">
                        <span class="text-gray-500">Customer</span>
                        <div class="text-right">
                            <span class="font-bold text-gray-900" x-text="customer.name || customer.phone || 'Walk-in Guest'"></span>
                            <div class="text-xs text-gray-500" x-show="customer.phone" x-text="customer.phone"></div>
                        </div>
                    </div>

                    <!-- Sale Date -->
                    <div class="flex items-center justify-between py-1 border-b border-gray-100">
                        <span class="text-gray-500">Sale Date</span>
                        <span class="font-semibold text-gray-800" x-text="saleDate"></span>
                    </div>

                    <!-- Payment Method -->
                    <div class="flex items-center justify-between py-1 border-b border-gray-100">
                        <span class="text-gray-500">Payment Mode</span>
                        <span class="font-bold uppercase px-2 py-0.5 rounded text-xs"
                              :class="{
                                  'bg-indigo-100 text-indigo-800': payment.method === 'upi',
                                  'bg-emerald-100 text-emerald-800': payment.method === 'cash',
                                  'bg-blue-100 text-blue-800': payment.method === 'card'
                              }"
                              x-text="payment.method"></span>
                    </div>

                    <!-- Total Items -->
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Total Items</span>
                        <span class="font-semibold text-gray-800" x-text="validCartItems.reduce((sum, item) => sum + parseInt(item.quantity), 0)"></span>
                    </div>

                    <!-- Subtotal -->
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Subtotal</span>
                        <span class="font-semibold text-gray-800" x-text="formatCurrency(subtotal)"></span>
                    </div>

                    <!-- Discounts -->
                    <div class="flex items-center justify-between text-red-600" x-show="totalDiscount > 0">
                        <span>Item Discounts</span>
                        <span class="font-semibold" x-text="'- ' + formatCurrency(totalDiscount)"></span>
                    </div>

                    <div class="flex items-center justify-between text-emerald-600 font-semibold" x-show="appliedCoupon">
                        <span x-text="'Coupon (' + (appliedCoupon ? appliedCoupon.code : '') + ')'"></span>
                        <span x-text="'- ' + formatCurrency(appliedCoupon ? appliedCoupon.discount_amount : 0)"></span>
                    </div>

                    <div class="flex items-center justify-between text-red-600" x-show="numericBillDiscount > 0">
                        <span>Other Discount</span>
                        <span class="font-semibold" x-text="'- ' + formatCurrency(numericBillDiscount)"></span>
                    </div>

                    <!-- Total -->
                    <div class="flex items-center justify-between border-t border-gray-200 pt-3 mt-2">
                        <span class="text-base font-bold text-gray-900">Total Bill</span>
                        <span class="text-xl font-extrabold text-blue-600" x-text="formatCurrency(total)"></span>
                    </div>

                    <!-- Cash Details (if Cash) -->
                    <template x-if="payment.method === 'cash'">
                        <div class="pt-2 border-t border-gray-100 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Cash Received</span>
                                <span class="font-semibold text-gray-800" x-text="formatCurrency(payment.cash_received)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 font-medium">Change Due</span>
                                <span class="font-bold text-emerald-600" x-text="formatCurrency(change)"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Actions -->
                <div class="mt-6 border-t border-gray-200 pt-4 space-y-3">
                    <!-- Status selector in modal -->
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1.5">Sale Status</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="saleStatus = 'completed'"
                                class="px-3 py-2 text-xs font-bold rounded-lg border flex items-center justify-center transition-colors"
                                :class="saleStatus === 'completed' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                            >
                                <svg x-show="saleStatus === 'completed'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                COMPLETED
                            </button>
                            <button
                                type="button"
                                @click="saleStatus = 'pending'"
                                class="px-3 py-2 text-xs font-bold rounded-lg border flex items-center justify-center transition-colors"
                                :class="saleStatus === 'pending' ? 'bg-amber-500 text-white border-amber-500 shadow-sm' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                            >
                                <svg x-show="saleStatus === 'pending'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                PENDING
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="submitSale()"
                        :disabled="isSubmitting"
                        class="w-full flex justify-center items-center py-3 px-4 rounded-xl shadow-sm text-base font-bold text-white focus:outline-none disabled:opacity-50 transition-colors"
                        :class="saleStatus === 'completed' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-700 hover:bg-gray-800'"
                    >
                        <template x-if="isSubmitting">
                            <svg class="animate-spin -ml-1 mr-2.5 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </template>
                        <span x-text="isSubmitting ? 'Processing Sale...' : 'PROCESS SALE'"></span>
                    </button>

                    <x-secondary-button
                        type="button"
                        @click="$dispatch('close-modal', { name: 'confirmation-modal' })"
                        class="w-full justify-center"
                    >
                        Back
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>

        <!-- Cancel Confirmation Modal -->
        <x-modal name="cancel-modal" focusable>
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">
                            Cancel Transaction?
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to cancel? All current cart items, customer details, and tender entries will be lost.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <x-danger-button @click="resetForm(); clearStorage(); $dispatch('close-modal', { name: 'cancel-modal' }); $dispatch('toast', { message: 'Transaction Cancelled', type: 'info' })" class="w-full sm:w-auto justify-center">
                    {{ __('Yes, Cancel Transaction') }}
                </x-danger-button>
                <button
                    type="button"
                    @click="$dispatch('close-modal', { name: 'cancel-modal' })"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto transition-colors"
                >
                    {{ __('No, Return') }}
                </button>
            </div>
        </x-modal>
    </div>
</x-app-layout>
