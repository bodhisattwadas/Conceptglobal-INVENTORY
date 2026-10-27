<x-modal name="product-form-modal" :title="''" maxWidth="2xl" :close-on-outside="false" :close-on-escape="false">
    <div class="p-6">
        <!-- Custom Header -->
        <div class="mb-6 flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
            <div class="space-y-1.5 text-center sm:text-left">
                <h3 class="text-lg font-semibold leading-none tracking-tight text-foreground">
                    {{ $isEditing ? 'Edit Product' : ($isCloning ? 'Clone Product' : 'Create Product') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ $isEditing ? 'Make changes to your product here. Click save when you\'re done.' : ($isCloning ? 'Product details have been copied without the barcode or SKU. Scan a new barcode or adjust details and save.' : 'Add a new product to your inventory.') }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                x-on:click="$dispatch('close-modal', { name: 'product-form-modal' })"
                aria-label="{{ __('Close') }}"
            >
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
        </div>

        <form method="POST" wire:submit.prevent="save" class="space-y-6">

            <div class="space-y-2">
                <x-input-label for="image" value="Product Image" hint="Image shown in product lists and detail views. Example: front-pack.jpg." />
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="space-y-2">
                        <div class="h-24 w-24 shrink-0 overflow-hidden rounded-md border border-gray-200 bg-gray-50 shadow-sm" style="width: 96px; height: 96px;">
                            <div wire:loading wire:target="image" class="flex h-full w-full items-center justify-center text-xs text-gray-400">
                                Loading...
                            </div>

                            <div wire:loading.remove wire:target="image" class="h-full w-full">
                                @if($image)
                                    <img
                                        wire:key="new-product-image-{{ $image->getFilename() }}"
                                        src="{{ $image->temporaryUrl() }}"
                                        alt="New product image preview"
                                        class="block h-24 w-24 object-contain"
                                        style="width: 96px; height: 96px; max-width: 96px; max-height: 96px;"
                                    >
                                @elseif($product?->image_url)
                                    <img
                                        wire:key="current-product-image-{{ $currentImagePath }}"
                                        src="{{ $product->image_url }}"
                                        alt="Current product image"
                                        class="block h-24 w-24 object-contain"
                                        style="width: 96px; height: 96px; max-width: 96px; max-height: 96px;"
                                    >
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">
                                        No image
                                    </div>
                                @endif
                            </div>
                        </div>
                        <p class="text-center text-xs font-medium text-gray-500">
                            @if($image)
                                New image preview
                            @elseif($currentImagePath)
                                Current image
                            @else
                                Thumbnail
                            @endif
                        </p>
                    </div>
                    <div class="flex-1 space-y-2">
                        <input
                            id="image"
                            type="file"
                            wire:model="image"
                            accept="image/png,image/jpeg,image/webp"
                            class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-foreground hover:file:bg-primary/90"
                        >
                        <p class="text-xs text-muted-foreground">PNG, JPG, or WEBP up to 2 MB.</p>
                        <div wire:loading wire:target="image" class="text-xs text-muted-foreground">Uploading image...</div>
                        <x-input-error :messages="$errors->get('image')" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- SKU -->
                @if($isEditing)
                    <x-form-input
                        name="sku"
                        label="SKU (Stock Keeping Unit)"
                        type="text"
                        wire:model="sku"
                        readonly
                        placeholder="e.g. SKU-1234-ABCD"
                        class="bg-muted text-muted-foreground cursor-not-allowed"
                        hint="Unique product code used for tracking. Example: SKU-1234-ABCD."
                    />
                @else
                    <!-- SKU Auto Generated -->
                    <div class="hidden">
                        <input type="hidden" wire:model="sku">
                    </div>
                @endif

                <!-- Product Code / Barcode -->
                <div class="{{ !$isEditing ? 'col-span-1' : '' }} space-y-2">
                    <x-input-label for="product_code" :value="__('Product Code / Barcode (UPC/EAN)')" hint="Barcode or UPC scanned via barcode reader. Example: 8906036215017." />
                    <x-text-input
                        id="product_code"
                        name="product_code"
                        placeholder="Scan barcode or enter code (e.g. 8906036215017)"
                        type="text"
                        wire:model.live.debounce.300ms="product_code"
                        x-on:keydown.enter.prevent="document.getElementById('name')?.focus()"
                        class="block w-full {{ $duplicateBarcodeWarning ? 'border-amber-400 focus:border-amber-500 focus:ring-amber-500 bg-amber-50/20' : '' }}"
                    />
                    <x-input-error :messages="$errors->get('product_code')" class="mt-1" />

                    @if($duplicateBarcodeWarning)
                        <div class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 shadow-sm" role="alert">
                            <svg class="h-4 w-4 shrink-0 text-amber-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-medium leading-relaxed">{{ $duplicateBarcodeWarning }}</span>
                        </div>
                    @endif
                </div>

                <!-- Name -->
                <x-form-input
                    name="name"
                    label="Product Name"
                    placeholder="e.g. Strawberry Hydrating & Softening Body Wash"
                    type="text"
                    wire:model="name"
                    required
                    class="{{ !$isEditing ? 'col-span-1' : 'col-span-2' }}"
                    hint="Customer-readable product name without barcode prefix. Example: Strawberry Hydrating & Softening Body Wash."
                />
            </div>

            <!-- Row 2: Brand, Category & Unit -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Brand -->
                <div class="space-y-2">
                    <x-input-label for="company_id" :value="__('Brand')" required hint="Brand or company this product belongs to. Example: Colorbar." />
                    <div wire:key="product-form-company-{{ $formKey }}">
                        <x-tom-select
                            id="company_id"
                            name="company_id"
                            wire:model="company_id"
                            :url="route('ajax.companies.search')"
                            method="POST"
                            placeholder="Select Brand"
                            data-initial-label="{{ $brandName }}"
                        />
                    </div>
                    <x-input-error :messages="$errors->get('company_id')" />
                </div>

                <!-- Category -->
                <div class="space-y-2">
                    <x-input-label for="category_id" :value="__('Category')" required hint="Product grouping for filtering and reports. Example: Lip Makeup." />
                    <div wire:key="product-form-category-{{ $formKey }}">
                        <x-tom-select
                            id="category_id"
                            name="category_id"
                            wire:model="category_id"
                            :url="route('ajax.categories.search')"
                            method="POST"
                            placeholder="Select Category"
                            data-initial-label="{{ $categoryName }}"
                        />
                    </div>
                    <x-input-error :messages="$errors->get('category_id')" />
                </div>

                <!-- Unit -->
                <div class="space-y-2">
                    <x-input-label for="unit_id" :value="__('Unit')" required hint="Measurement unit used for quantities. Example: pcs." />
                    <div wire:key="product-form-unit-{{ $formKey }}">
                        <x-tom-select
                            id="unit_id"
                            name="unit_id"
                            wire:model="unit_id"
                            :url="route('ajax.units.search')"
                            method="POST"
                            placeholder="Select Unit"
                            data-initial-label="{{ $unitName }}"
                        />
                    </div>
                    <x-input-error :messages="$errors->get('unit_id')" />
                </div>
            </div>

            <!-- MRP -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <x-input-label for="mrp" :value="__('MRP') . ' (' . \App\Models\Setting::get('currency_symbol', 'Rp') . ')'" hint="Maximum retail price before discounts. Example: 999." />
                    <x-currency-input
                        id="mrp"
                        wire:model.live.debounce.500ms="mrp"
                        placeholder="0"
                        required
                    />
                    <x-input-error :messages="$errors->get('mrp')" />
                </div>
            </div>

            <!-- Minimum Quantity and Active Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Minimum Quantity -->
                <x-form-input
                    name="min_stock"
                    label="Minimum Quantity"
                    type="number"
                    wire:model="min_stock"
                    min="0"
                    placeholder="0"
                    required
                    hint="Reorder alert level for inventory. Example: 10."
                />

                <!-- Is Active -->
                <div class="flex items-center h-full pt-8">
                    <label class="inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="is_active"
                            class="w-6 h-6 rounded-full border-2 border-primary text-primary focus:ring-primary/20"
                        >
                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Active') }}
                        </span>
                    </label>
                </div>
            </div>

            <!-- Description -->
            <div class="space-y-2">
                <x-input-label for="description" value="Description" hint="Product details visible to users. Example: Long-wear matte finish." />
                <textarea
                    id="description"
                    wire:model="description"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Optional description..."
                ></textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <!-- Notes -->
            <div class="space-y-2">
                <x-input-label for="notes" value="Internal Notes" hint="Private notes for staff. Example: Supplier revised price in July." />
                <textarea
                    id="notes"
                    wire:model="notes"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Internal pricing history & notes..."
                ></textarea>
                <x-input-error :messages="$errors->get('notes')" />
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-gray-200">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-form-modal' })">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-primary-button type="submit" wire:loading.attr="disabled">
                    <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <x-heroicon-o-check wire:loading.remove wire:target="save" class="w-4 h-4 mr-2" />
                    {{ $isEditing ? __('Save Changes') : __('Create Product') }}
                </x-primary-button>
            </div>
        </form>

        @if($isEditing && $product)
            <div class="mt-6">
                @include('products.partials.price-history', ['product' => $product])
            </div>
        @endif
    </div>
</x-modal>
