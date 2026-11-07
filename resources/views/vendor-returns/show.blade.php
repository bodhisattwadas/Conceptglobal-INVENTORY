<x-app-layout title="Vendor Return Details">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-semibold text-xl text-foreground leading-tight">
                        {{ __('Vendor Return') }} #{{ $vendorReturn->return_number }}
                    </h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">
                        Completed
                    </span>
                </div>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Processed on {{ $vendorReturn->return_date?->format('d M Y') }} by {{ $vendorReturn->creator?->name }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('vendor-returns.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shadow-xs transition-colors">
                    <x-heroicon-o-arrow-left class="w-4 h-4" />
                    {{ __('Back') }}
                </a>
                <a href="{{ route('vendor-returns.print', $vendorReturn) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md bg-rose-600 text-white hover:bg-rose-700 shadow-sm transition-colors">
                    <x-heroicon-o-printer class="w-4 h-4" />
                    {{ __('Print Debit Note') }}
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

            <!-- Top Grid: Metadata & Vendor Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Card 1: Return Summary -->
                <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-3">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100 pb-2">
                        Return Details
                    </h3>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-gray-500 block">Return Number:</span>
                            <span class="font-bold text-gray-900 font-mono">{{ $vendorReturn->return_number }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Return Date:</span>
                            <span class="font-semibold text-gray-900">{{ $vendorReturn->return_date?->format('d M Y') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Primary Reason:</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800">
                                {{ $vendorReturn->reason_label }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Location:</span>
                            <span class="font-semibold text-gray-900 uppercase">{{ $vendorReturn->location }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Created By:</span>
                            <span class="font-semibold text-gray-900">{{ $vendorReturn->creator?->name ?: '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Total Debit Value:</span>
                            <span class="font-bold text-rose-700 text-sm">{{ $vendorReturn->formatted_total }}</span>
                        </div>
                    </div>
                    @if($vendorReturn->notes)
                        <div class="pt-2 border-t border-gray-100 text-xs">
                            <span class="text-gray-500 block">Notes:</span>
                            <p class="text-gray-700 italic mt-0.5">{{ $vendorReturn->notes }}</p>
                        </div>
                    @endif
                </div>

                <!-- Card 2: Supplier & Original PO -->
                <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-3">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100 pb-2">
                        Supplier & Purchase Order
                    </h3>
                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="text-gray-500 block">Supplier / Vendor:</span>
                            <a href="{{ route('suppliers.show', $vendorReturn->supplier) }}" class="font-bold text-indigo-600 hover:underline text-sm">
                                {{ $vendorReturn->supplier?->name }}
                            </a>
                        </div>
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <span class="text-gray-500 block">Original Purchase Order:</span>
                                <a href="{{ route('purchases.show', $vendorReturn->purchase) }}" class="font-mono font-semibold text-gray-900 hover:text-indigo-600 hover:underline">
                                    {{ $vendorReturn->purchase?->invoice_number ?: 'PO #'.$vendorReturn->purchase_id }}
                                </a>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Brand / Company:</span>
                                <span class="font-semibold text-gray-900">{{ $vendorReturn->company?->company_name ?: '-' }}</span>
                            </div>
                        </div>
                        @if($vendorReturn->purchase?->vendorInvoice)
                            <div class="pt-2 border-t border-gray-100 flex items-center justify-between">
                                <span class="text-gray-500">Linked Vendor Invoice:</span>
                                <a href="{{ route('vendor-invoices.show', $vendorReturn->purchase->vendorInvoice) }}" class="font-semibold text-rose-600 hover:underline">
                                    {{ $vendorReturn->purchase->vendorInvoice->invoice_number ?: 'Invoice #'.$vendorReturn->purchase->vendorInvoice->id }}
                                    (Due: {{ format_money(max(0, $vendorReturn->purchase->vendorInvoice->amount - $vendorReturn->purchase->vendorInvoice->paid_amount)) }})
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-xl shadow-xs border border-gray-200 p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">
                        Returned Products ({{ $vendorReturn->items->count() }})
                    </h3>
                    <span class="text-xs text-gray-500">Stock decremented from respective batches</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-gray-600">Product</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-gray-600">SKU</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-gray-600">Batch Number</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-gray-600">Returned Qty</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-gray-600">Unit Price</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-gray-600">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($vendorReturn->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900">{{ $item->product?->name }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-gray-500">{{ $item->product?->sku }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-700 font-semibold">{{ $item->batch_number ?: '-' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            - {{ $item->quantity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-700">{{ format_money($item->unit_price) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-rose-700">{{ format_money($item->subtotal) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-200 font-bold">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right text-gray-700">Total Units: {{ $vendorReturn->items->sum('quantity') }}</td>
                                <td colspan="2" class="px-4 py-3 text-right text-gray-900">Total Debit Note Amount:</td>
                                <td class="px-4 py-3 text-right text-base text-rose-700">{{ $vendorReturn->formatted_total }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
