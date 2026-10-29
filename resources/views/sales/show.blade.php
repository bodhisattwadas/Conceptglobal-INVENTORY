<x-app-layout title="Sale Details">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Sale Details') }} #{{ $sale->invoice_number ?: $sale->id }}
            </h2>
            <div class="flex items-center gap-2">
                <x-secondary-button href="{{ route('sales.index') }}">
                    &larr; {{ __('Back to List') }}
                </x-secondary-button>
                <x-secondary-button type="button" x-data x-on:click="$dispatch('open-modal', { name: 'edit-date-modal' })">
                    <x-heroicon-o-calendar class="w-4 h-4 mr-2 text-indigo-600" />
                    {{ __('Edit Date') }}
                </x-secondary-button>
                @if(auth()->user()?->isAdmin() && $sale->status !== \App\Enums\SaleStatus::CANCELLED)
                    <x-secondary-button href="{{ route('sales.edit', $sale) }}">
                        <x-heroicon-o-pencil-square class="w-4 h-4 mr-2 text-amber-600" />
                        {{ __('Edit Sale') }}
                    </x-secondary-button>
                @endif
                <x-primary-button href="{{ route('sales.print', $sale) }}" target="_blank">
                    <x-heroicon-o-printer class="w-4 h-4 mr-2" />
                    {{ __('Print Invoice') }}
                </x-primary-button>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Main Info Card -->
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-200">
                <div class="p-6">
                    <!-- Header Info -->
                    <div class="flex items-start justify-between border-b border-gray-100 pb-4 mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ __('Sale Information') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Details of the sales transaction') }}</p>
                        </div>
                        <div class="px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200">
                            ID: #{{ $sale->id }}
                        </div>
                    </div>

                    <!-- Content Grid -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Customer -->
                        <x-detail-item label="Customer" :value="$sale->customer->name ?? 'Guest'">
                            <x-heroicon-o-user class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <!-- Invoice -->
                        <x-detail-item label="Invoice Number" :value="$sale->invoice_number ?? '-'">
                            <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                        </x-detail-item>

                        <!-- Sale Date -->
                        <div class="flex items-start justify-between">
                            <x-detail-item label="Sale Date" :value="$sale->sale_date->format('d M Y')">
                                <x-heroicon-o-calendar class="w-4 h-4 text-gray-400" />
                            </x-detail-item>
                            <button
                                type="button"
                                x-data
                                x-on:click="$dispatch('open-modal', { name: 'edit-date-modal' })"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shadow-xs transition mt-1"
                                title="{{ __('Edit Sale Date') }}"
                            >
                                <x-heroicon-o-pencil-square class="w-3.5 h-3.5 text-indigo-600" />
                                {{ __('Edit Date') }}
                            </button>
                        </div>

                        <!-- Payment Method -->
                        <div class="flex items-start justify-between">
                            <x-detail-item label="Payment Method" :value="$sale->payment_method->label()">
                                <x-heroicon-o-credit-card class="w-4 h-4 text-gray-400" />
                            </x-detail-item>
                            @if(auth()->user()?->isAdmin() && $sale->status !== \App\Enums\SaleStatus::CANCELLED)
                                <button
                                    type="button"
                                    x-data
                                    x-on:click="$dispatch('open-modal', { name: 'edit-payment-modal' })"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shadow-xs transition mt-1"
                                    title="{{ __('Edit Payment Method') }}"
                                >
                                    <x-heroicon-o-pencil-square class="w-3.5 h-3.5 text-indigo-600" />
                                    {{ __('Edit Payment') }}
                                </button>
                            @endif
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="text-sm font-medium leading-none text-gray-500">Status</label>
                            <div class="mt-1">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $sale->status->color() }}">
                                    {{ $sale->status->label() }}
                                </span>
                            </div>
                        </div>



                        <!-- Created By -->
                        <x-detail-item label="Created By" :value="$sale->creator->name ?? 'Unknown'">
                            <x-heroicon-o-user class="w-4 h-4 text-gray-400" />
                        </x-detail-item>
                    </div>

                    <!-- Notes -->
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <div class="space-y-1">
                            <label class="text-sm font-medium leading-none text-gray-500">
                                Notes
                            </label>
                            <div class="bg-gray-50 p-3 rounded-md border border-gray-100">
                                <p class="text-sm text-slate-700 italic leading-relaxed">{{ $sale->notes ?: 'No additional notes.' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table Section -->
                    <div class="mt-6 border-t overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3">Code</th>
                                    <th class="px-6 py-3">Product</th>
                                    <th class="px-6 py-3">FIFO Batches</th>
                                    <th class="px-6 py-3">Unit</th>
                                    <th class="px-6 py-3 text-center">Qty</th>
                                    <th class="px-6 py-3 text-right">Price</th>
                                    <th class="px-6 py-3 text-right">Discount</th>
                                    <th class="px-6 py-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @php
                                    $sortedItems = $sale->items->sortBy(fn($item) => $item->service_id ? 1 : 0);
                                @endphp
                                @foreach($sortedItems as $item)
                                    <tr class="bg-white hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $item->isService() ? ($item->service?->code ?? '-') : ($item->product?->product_code ?? $item->product?->sku ?? '-') }}
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            {{ $item->isService() ? $item->service?->name : $item->product?->name }}
                                            @if($item->isService())
                                                <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-700">Service</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            @php
                                                $batchMovements = $item->inventoryMovements
                                                    ->filter(fn ($movement) => in_array($movement->type, ['sale', 'sale_restore'], true) && $movement->quantity < 0)
                                                    ->sortBy('created_at')
                                                    ->values();
                                            @endphp

                                            @forelse($batchMovements as $movement)
                                                <div class="font-mono text-xs">
                                                    <span class="font-semibold text-gray-900">{{ $movement->purchaseItem?->batch_number ?: 'Batch' }}</span>
                                                    <span class="text-gray-500">:</span>
                                                    <span class="font-semibold text-red-700">{{ abs($movement->quantity) }}</span>
                                                    @if($movement->purchaseItem?->purchase)
                                                        <span class="text-gray-400">/ {{ $movement->purchaseItem->purchase->invoice_number ?: 'PO-'.$movement->purchaseItem->purchase->id }}</span>
                                                    @endif
                                                </div>
                                            @empty
                                                <span class="text-gray-400">-</span>
                                            @endforelse
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ $item->isService() ? 'srv' : ($item->product?->unit->symbol ?? $item->product?->unit->name ?? '-') }}
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            {{ number_format($item->quantity) }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            @money($item->unit_price)
                                        </td>
                                        <td class="px-6 py-4 text-right text-red-500">
                                            {!! $item->discount > 0 ? "- <span>" . format_money($item->discount) . "</span>" : '-' !!}
                                        </td>
                                        <td class="px-6 py-4 text-right font-medium">
                                            @money($item->subtotal)
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-bold">
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-right">Subtotal</td>
                                    <td class="px-6 py-4 text-right text-gray-700">
                                        @money($sale->subtotal)
                                    </td>
                                </tr>
                                @php
                                    $productItemDiscount = $sale->items->filter(fn($i) => empty($i->service_id))->sum(fn($i) => (float)$i->discount * (int)$i->quantity);
                                    $serviceItemDiscount = $sale->items->filter(fn($i) => !empty($i->service_id))->sum(fn($i) => (float)$i->discount * (int)$i->quantity);
                                @endphp
                                @if($productItemDiscount > 0)
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-right text-red-600">Product Discount</td>
                                        <td class="px-6 py-4 text-right text-red-600">
                                            - @money($productItemDiscount)
                                        </td>
                                    </tr>
                                @endif
                                @if($serviceItemDiscount > 0)
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-right text-purple-600">Service Discount</td>
                                        <td class="px-6 py-4 text-right text-purple-600">
                                            - @money($serviceItemDiscount)
                                        </td>
                                    </tr>
                                @endif
                                @php
                                    $hasCoupon = ($sale->coupon_discount > 0) || ($sale->coupon_code && $sale->coupon_discount == 0 && $sale->global_discount > 0 && $sale->round_off == 0);
                                    $couponDiscountAmount = $sale->coupon_discount > 0 ? $sale->coupon_discount : ($hasCoupon ? $sale->global_discount : 0);
                                    $hasRoundOff = ($sale->round_off != 0) || (!$sale->coupon_code && $sale->global_discount != 0 && $sale->round_off == 0);
                                    $roundOffAmount = $sale->round_off != 0 ? $sale->round_off : ($hasRoundOff ? -$sale->global_discount : 0);
                                @endphp
                                @if($hasCoupon && $couponDiscountAmount > 0)
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-right text-emerald-600">
                                            Coupon ({{ $sale->coupon_code }})
                                        </td>
                                        <td class="px-6 py-4 text-right text-emerald-600">
                                            - @money($couponDiscountAmount)
                                        </td>
                                    </tr>
                                @endif
                                @if($hasRoundOff && $roundOffAmount != 0)
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-right {{ $roundOffAmount < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                            Round Off
                                        </td>
                                        <td class="px-6 py-4 text-right {{ $roundOffAmount < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                            {{ $roundOffAmount < 0 ? '- ' : '+ ' }}@money(abs($roundOffAmount))
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-right">Total</td>
                                    <td class="px-6 py-4 text-right text-indigo-600 text-lg">
                                        @money($sale->total)
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-right text-gray-600">Cash Received</td>
                                    <td class="px-6 py-4 text-right text-gray-800">
                                        @money($sale->cash_received)
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-right text-gray-600">Change</td>
                                    <td class="px-6 py-4 text-right text-green-600">
                                        @money($sale->change)
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Action Buttons Workflow -->
            <div x-data="{
                actionUrl: '',
                actionMethod: '',
                modalTitle: '',
                modalMessage: '',
                confirmButtonText: '',
                confirmButtonClass: '',

                confirmAction(url, method, title, message, btnText, btnClass) {
                    this.actionUrl = url;
                    this.actionMethod = method;
                    this.modalTitle = title;
                    this.modalMessage = message;
                    this.confirmButtonText = btnText;
                    this.confirmButtonClass = btnClass;
                    $dispatch('open-modal', { name: 'confirmation-modal' });
                }
            }" class="flex flex-col sm:flex-row justify-end gap-4">

                @if($sale->status === \App\Enums\SaleStatus::PENDING)
                    {{-- Complete / Pay Action --}}
                    <x-primary-button
                        class="!bg-green-600 hover:!bg-green-700 focus:!ring-green-500"
                        @click="confirmAction('{{ route('sales.complete', $sale) }}', 'PATCH', 'Complete Sale', 'Mark this sale as Completed? This confirms payment has been received.', 'Complete Sale', '!bg-green-600 hover:!bg-green-700 focus:!ring-green-500')"
                    >
                        {{ __('Complete Sale') }}
                    </x-primary-button>

                    {{-- Cancel Pending Action (Modal) --}}
                    <div x-data="{ cancelOpen: false }">
                        <x-danger-button @click="cancelOpen = true">
                            {{ __('Cancel Sale') }}
                        </x-danger-button>

                        <!-- Cancel Modal -->
                        <div x-show="cancelOpen"
                             style="display: none;"
                             x-transition.opacity
                             class="fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-75 flex items-center justify-center p-4">

                            <div @click.outside="cancelOpen = false"
                                 x-transition.scale
                                 class="relative bg-white rounded-lg max-w-md w-full p-6 shadow-xl text-left">

                                <h3 class="text-lg font-medium text-gray-900 mb-2">
                                    {{ __('Cancel Pending Sale') }}
                                </h3>
                                <p class="text-sm text-gray-500 mb-4">
                                    {{ __('Are you sure you want to cancel this pending sale? Please provide a reason.') }}
                                </p>

                                <form action="{{ route('sales.destroy', $sale) }}" method="POST">
                                    @csrf
                                    @method('DELETE')

                                    <div class="mb-4">
                                        <x-input-label for="reason" :value="__('Reason')" hint="Optional note explaining why this sale action is being taken. Example: Customer requested cancellation." />
                                        <textarea
                                            name="reason"
                                            id="reason"
                                            rows="3"
                                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            placeholder="Customer changed mind..."
                                            required
                                        ></textarea>
                                    </div>

                                    <div class="mt-6 flex justify-end gap-3">
                                        <x-secondary-button type="button" @click="cancelOpen = false">
                                            {{ __('Back') }}
                                        </x-secondary-button>
                                        <x-danger-button type="submit">
                                            {{ __('Cancel Sale') }}
                                        </x-danger-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if($sale->status === \App\Enums\SaleStatus::COMPLETED)
                    {{-- Cancel Action --}}
                    <x-secondary-button
                        class="text-red-600 hover:bg-red-50 border-red-200"
                        @click="confirmAction('{{ route('sales.destroy', $sale) }}', 'DELETE', 'Cancel Sale', 'Are you sure you want to cancel (VOID) this sale? Stocks will be returned.', 'Yes, Cancel Sale', '!bg-red-600 hover:!bg-red-700 focus:!ring-red-500')"
                    >
                        {{ __('Cancel Sale') }}
                    </x-secondary-button>
                @endif

                @if($sale->status === \App\Enums\SaleStatus::CANCELLED)
                    {{-- Restore Action --}}
                    <x-secondary-button
                        class="bg-gray-800 text-white hover:bg-gray-700 focus:ring-gray-500"
                        @click="confirmAction('{{ route('sales.restore', $sale) }}', 'PATCH', 'Restore Sale', 'Restore this sale to Pending status? You can then complete it again.', 'Restore to Pending', '!bg-gray-800 hover:!bg-gray-700 text-white')"
                    >
                        {{ __('Restore to Pending') }}
                    </x-secondary-button>
                @endif

                <!-- Shared Confirmation Modal -->
                <x-modal name="confirmation-modal">
                    <div class="p-6" x-data="{ submitting: false }">
                        <h2 class="text-lg font-medium text-gray-900" x-text="modalTitle"></h2>

                        <p class="mt-1 text-sm text-gray-600" x-text="modalMessage"></p>

                        @if($sale->status === \App\Enums\SaleStatus::PENDING && $sale->payment_method === \App\Enums\PaymentMethod::CASH)
                            <template x-if="actionUrl.endsWith('/complete')">
                                <div class="mt-5">
                                    <x-input-label for="cash_received" :value="__('Cash Received')" hint="Enter the amount collected before completing this sale." />
                                    <input
                                        id="cash_received"
                                        name="cash_received"
                                        type="number"
                                        min="{{ $sale->total }}"
                                        step="1"
                                        value="{{ max($sale->cash_received, $sale->total) }}"
                                        required
                                        form="complete-sale-form"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                </div>
                            </template>
                        @endif

                        <div class="mt-6 flex justify-end">
                            <x-secondary-button x-on:click="$dispatch('close-modal', { name: 'confirmation-modal' })" x-bind:disabled="submitting">
                                {{ __('Back') }}
                            </x-secondary-button>

                            <form id="complete-sale-form" :action="actionUrl" method="POST" class="ml-3" @submit="submitting = true">
                                @csrf
                                <input type="hidden" name="_method" :value="actionMethod">

                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 h-10 px-4 py-2 text-white shadow-sm bg-primary"
                                    x-bind:class="confirmButtonClass + (submitting ? ' opacity-75 cursor-not-allowed' : '')"
                                    x-bind:disabled="submitting"
                                >
                                    <svg x-show="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="confirmButtonText"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                </x-modal>

                <!-- Edit Payment Method Modal -->
                @if(auth()->user()?->isAdmin() && $sale->status !== \App\Enums\SaleStatus::CANCELLED)
                @php
                    $showPaymentRef = '';
                    if (preg_match('/Ref:\s*([^\|\n\r]+)/i', $sale->notes ?? '', $m)) {
                        $showPaymentRef = trim($m[1]);
                    }
                @endphp
                <x-modal name="edit-payment-modal" maxWidth="md">
                    <form
                        action="{{ route('sales.update-payment-method', $sale) }}"
                        method="POST"
                        class="p-6"
                        x-data="{
                            method: '{{ $sale->payment_method->value }}',
                            paymentRef: '{{ addslashes($showPaymentRef) }}',
                            cashReceived: {{ (float) ($sale->cash_received ?: $sale->total) }},
                            total: {{ (float) $sale->total }}
                        }"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="flex items-start justify-between border-b border-gray-100 pb-4 mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('Edit Payment Method') }}</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Invoice: <span class="font-mono font-bold">{{ $sale->invoice_number ?: '#'.$sale->id }}</span> (Total: <span class="font-mono font-bold text-indigo-700">₹{{ number_format((float) $sale->total, 2) }}</span>)</p>
                            </div>
                            <button
                                type="button"
                                x-on:click="$dispatch('close-modal', { name: 'edit-payment-modal' })"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-400 hover:text-gray-600 hover:bg-gray-50"
                            >
                                <x-heroicon-o-x-mark class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700">{{ __('Payment Method') }}</label>
                                    <span class="text-xs text-gray-400 font-medium">Selected: <strong class="text-indigo-600 capitalize" x-text="method"></strong></span>
                                </div>

                                <!-- 3-4 Payment Method Buttons: UPI / Cash / Card / Transfer -->
                                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                    <!-- UPI -->
                                    <button
                                        type="button"
                                        @click="method = 'upi'"
                                        class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-lg border-2 transition-all font-bold text-xs"
                                        :class="method === 'upi' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                    >
                                        <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                        <span>UPI</span>
                                    </button>

                                    <!-- Cash -->
                                    <button
                                        type="button"
                                        @click="method = 'cash'"
                                        class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-lg border-2 transition-all font-bold text-xs"
                                        :class="method === 'cash' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                    >
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                        <span>Cash</span>
                                    </button>

                                    <!-- Card -->
                                    <button
                                        type="button"
                                        @click="method = 'card'"
                                        class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-lg border-2 transition-all font-bold text-xs"
                                        :class="method === 'card' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                    >
                                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                        <span>Card</span>
                                    </button>

                                    <!-- Bank Transfer -->
                                    <button
                                        type="button"
                                        @click="method = 'transfer'"
                                        class="flex items-center justify-center gap-1.5 py-2 px-2 rounded-lg border-2 transition-all font-bold text-xs"
                                        :class="method === 'transfer' ? 'border-indigo-600 bg-indigo-50/80 text-indigo-700 shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                    >
                                        <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                        <span>Transfer</span>
                                    </button>
                                </div>

                                <input type="hidden" name="payment_method" :value="method">
                            </div>

                            <!-- If CARD -->
                            <div x-show="method === 'card'" class="bg-blue-50/60 rounded-lg p-2.5 border border-blue-100 space-y-1.5">
                                <div class="flex items-center gap-1.5 text-blue-700 text-xs font-semibold">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                    <span>{{ __('Swipe / Tap on POS machine') }}</span>
                                </div>
                                <input
                                    type="text"
                                    name="payment_ref"
                                    x-model="paymentRef"
                                    placeholder="{{ __('Card 4 Digits / Auth Ref (Optional)...') }}"
                                    class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                                >
                            </div>

                            <!-- If UPI -->
                            <div x-show="method === 'upi'" class="bg-indigo-50/60 rounded-lg p-2.5 border border-indigo-100 space-y-1.5">
                                <div class="flex items-center gap-1.5 text-indigo-700 text-xs font-semibold">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>{{ __('Collect via QR Code / UPI App') }}</span>
                                </div>
                                <input
                                    type="text"
                                    name="payment_ref"
                                    x-model="paymentRef"
                                    placeholder="{{ __('UPI UTR / Reference ID (Optional)...') }}"
                                    class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                                >
                            </div>

                            <!-- If TRANSFER -->
                            <div x-show="method === 'transfer'" class="bg-purple-50/60 rounded-lg p-2.5 border border-purple-100 space-y-1.5">
                                <div class="flex items-center gap-1.5 text-purple-700 text-xs font-semibold">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                    <span>{{ __('Bank Transfer / NEFT / IMPS Reference') }}</span>
                                </div>
                                <input
                                    type="text"
                                    name="payment_ref"
                                    x-model="paymentRef"
                                    placeholder="{{ __('Bank Transfer Reference (Optional)...') }}"
                                    class="block w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5"
                                >
                            </div>

                            <!-- If CASH -->
                            <div x-show="method === 'cash'" class="p-3 bg-emerald-50 rounded-lg border border-emerald-200 space-y-2">
                                <div class="flex justify-between items-center">
                                    <label class="block text-xs font-bold text-emerald-800 uppercase">{{ __('Cash Received (₹)') }}</label>
                                    <button type="button" @click="cashReceived = total" class="text-xs text-emerald-700 underline font-semibold">Exact Amount</button>
                                </div>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="cash_received"
                                    x-model.number="cashReceived"
                                    class="w-full text-sm border-gray-300 rounded px-2.5 py-1.5 text-right font-bold"
                                >
                                <div class="flex justify-between text-xs text-emerald-700 font-semibold pt-1">
                                    <span>{{ __('Change to Return:') }}</span>
                                    <span class="font-mono" x-text="'₹' + Math.max(0, cashReceived - total).toFixed(2)"></span>
                                </div>
                            </div>

                            <!-- Reason for changing payment method -->
                            <div>
                                <x-input-label for="edit_payment_reason" :value="__('Reason for Modification')" />
                                <input
                                    id="edit_payment_reason"
                                    type="text"
                                    name="reason"
                                    placeholder="{{ __('e.g., Customer paid via UPI instead of Cash') }}"
                                    class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                            </div>

                            <div class="rounded-md bg-amber-50 p-3 border border-amber-200">
                                <p class="text-xs text-amber-800">
                                    {{ __('Updating the payment method will adjust the accounting journal and generate an audit log entry.') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                            <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'edit-payment-modal' })">
                                {{ __('Cancel') }}
                            </x-secondary-button>

                            <x-primary-button type="submit">
                                <x-heroicon-o-check class="w-4 h-4 mr-1.5" />
                                {{ __('Save Changes') }}
                            </x-primary-button>
                        </div>
                    </form>
                </x-modal>
                @endif

                <!-- Edit Sale Date Modal -->
                <x-modal name="edit-date-modal" maxWidth="md">
                    <form action="{{ route('sales.update-date', $sale) }}" method="POST" class="p-6">
                        @csrf
                        @method('PATCH')

                        <div class="flex items-start justify-between border-b border-gray-100 pb-4 mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('Edit Sale Date') }}</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Invoice: <span class="font-mono font-bold">{{ $sale->invoice_number ?: '#'.$sale->id }}</span></p>
                            </div>
                            <button
                                type="button"
                                x-on:click="$dispatch('close-modal', { name: 'edit-date-modal' })"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-400 hover:text-gray-600 hover:bg-gray-50"
                            >
                                <x-heroicon-o-x-mark class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <x-input-label for="edit_sale_date" :value="__('Sale Date')" required hint="Select the new date for this sales record." />
                                <input
                                    id="edit_sale_date"
                                    type="date"
                                    name="sale_date"
                                    value="{{ old('sale_date', $sale->sale_date->format('Y-m-d')) }}"
                                    required
                                    class="block w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-medium"
                                >
                                <x-input-error :messages="$errors->get('sale_date')" class="mt-1" />
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
                            <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'edit-date-modal' })">
                                {{ __('Cancel') }}
                            </x-secondary-button>

                            <x-primary-button type="submit">
                                <x-heroicon-o-check class="w-4 h-4 mr-1.5" />
                                {{ __('Save Changes') }}
                            </x-primary-button>
                        </div>
                    </form>
                </x-modal>

            </div>
        </div>
    </div>
</x-app-layout>
