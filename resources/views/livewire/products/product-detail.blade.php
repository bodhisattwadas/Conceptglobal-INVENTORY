<x-modal name="product-detail-modal" focusable>
    @if($product)
        <div class="p-6 space-y-6">
            <!-- Header -->
            <div class="flex items-start justify-between border-b border-border pb-4 gap-4">
                <div>
                    <h3 class="text-xl font-bold text-foreground tracking-tight">{{ $product->name }}</h3>
                    <p class="text-xs text-muted-foreground font-mono mt-0.5">SKU: {{ $product->sku }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($product->is_active)
                        <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 ring-1 ring-inset ring-green-600/20">
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20">
                            Inactive
                        </span>
                    @endif
                    <button type="button" x-on:click="$dispatch('close-modal', { name: 'product-detail-modal' })" class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-md hover:bg-gray-100" title="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Top Big Barcode Banner -->
            <div class="rounded-xl border-2 border-indigo-200 bg-gradient-to-r from-indigo-50 via-blue-50 to-indigo-50/60 p-4 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-sm shrink-0">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h3m12 0h3M3 20h3m12 0h3M7 8v8m3-8v8m4-8v8m3-8v8" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-700 block">
                                {{ __('Product Barcode / Code') }}
                            </span>
                            <div class="text-2xl sm:text-3xl font-black font-mono tracking-wider text-gray-900 select-all">
                                {{ $product->product_code ?: ($product->sku ?: '-') }}
                            </div>
                        </div>
                    </div>
                    @if($product->product_code || $product->sku)
                        <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
                            <button
                                type="button"
                                x-data="{ copied: false }"
                                @click="navigator.clipboard.writeText('{{ $product->product_code ?: $product->sku }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 hover:border-indigo-300 shadow-xs transition-colors"
                                title="Copy barcode to clipboard"
                            >
                                <svg x-show="!copied" class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <svg x-show="copied" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span x-text="copied ? 'Copied!' : 'Copy Code'"></span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                @if($product->image_url)
                    <div class="overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="max-h-64 w-full object-contain">
                    </div>
                @endif

                <!-- Details -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="space-y-1">
                        <x-input-label value="{{ __('Product Code / Barcode') }}" hint="Scanned barcode (UPC/EAN)." class="text-muted-foreground" />
                        <p class="text-sm font-mono font-bold text-foreground">{{ $product->product_code ?: '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <x-input-label value="{{ __('Brand') }}" hint="Brand or company this product belongs to. Example: Colorbar." class="text-muted-foreground" />
                        <p class="text-sm text-foreground font-medium">{{ $product->company?->short_name ?: ($product->company?->company_name ?? '-') }}</p>
                    </div>

                    <div class="space-y-1">
                        <x-input-label value="{{ __('Category') }}" hint="Product grouping for filtering and reports. Example: Lip Makeup." class="text-muted-foreground" />
                        <p class="text-sm text-foreground font-medium">{{ $product->category->name ?? '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <x-input-label value="{{ __('Unit') }}" hint="Measurement unit used for quantities. Example: pcs." class="text-muted-foreground" />
                        <p class="text-sm text-foreground font-medium">
                            @if($product->unit)
                                {{ $product->unit->name }} <span class="text-muted-foreground">({{ $product->unit->symbol }})</span>
                            @else
                                -
                            @endif
                        </p>
                    </div>

                    <div class="space-y-1">
                        <x-input-label value="{{ __('MRP') }}" hint="Maximum retail price before discounts. Example: 999." class="text-muted-foreground" />
                        <p class="text-sm text-foreground font-medium">@money($product->mrp)</p>
                    </div>

                    <div class="space-y-1">
                        <x-input-label value="{{ __('Minimum Quantity') }}" hint="Reorder alert level for inventory. Example: 10." class="text-muted-foreground" />
                        <p class="text-sm text-foreground font-medium">{{ $product->min_stock }}</p>
                    </div>
                </div>

                <div class="space-y-1">
                    <x-input-label value="{{ __('Description') }}" hint="Product details visible to users. Example: Long-wear matte finish." class="text-muted-foreground" />
                    <p class="text-sm text-foreground font-medium">
                        {{ $product->description ?: 'No description provided.' }}
                    </p>
                </div>

                <div class="space-y-1">
                    <x-input-label value="{{ __('Internal Notes') }}" hint="Private notes for staff. Example: Supplier revised price in July." class="text-muted-foreground" />
                    <div class="bg-gray-50 border border-secondary p-3 rounded-md">
                        <p class="text-sm text-foreground font-mono whitespace-pre-wrap leading-relaxed">{{ $product->notes ?: 'No notes.' }}</p>
                    </div>
                </div>

                <!-- Meta -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Created At') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $product->created_at?->format('d M Y, H:i') ?? '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Last Updated') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $product->updated_at?->format('d M Y, H:i') ?? '-' }}</p>
                    </div>
                </div>

                @include('products.partials.price-history', ['product' => $product])
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-x-2 pt-4 border-t border-border">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-detail-modal' })">
                    {{ __('Close') }}
                </x-secondary-button>
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-detail-modal' }); $dispatch('clone-product', { product: {{ $product->id }} })">
                    <x-heroicon-o-document-duplicate class="w-4 h-4 mr-2" />
                    {{ __('Clone Product') }}
                </x-secondary-button>
                <x-primary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-detail-modal' }); $dispatch('edit-product', { product: {{ $product->id }} })">
                    <x-heroicon-o-pencil-square class="w-4 h-4 mr-2" />
                    {{ __('Edit Product') }}
                </x-primary-button>
            </div>
        </div>
    @else
        <div class="p-8 text-center flex flex-col items-center justify-center space-y-3">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            <span class="text-sm text-muted-foreground">{{ __('Loading details...') }}</span>
        </div>
    @endif
</x-modal>
