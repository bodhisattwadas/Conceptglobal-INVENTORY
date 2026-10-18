<x-modal name="coupon-form-modal" :title="''" maxWidth="2xl">
    <div class="p-6">
        <!-- Custom Header -->
        <div class="mb-6 space-y-1.5 text-center sm:text-left border-b border-gray-200 pb-4">
            <h3 class="text-lg font-semibold leading-none tracking-tight text-foreground">
                {{ $isEditing ? 'Edit Coupon' : 'Create Coupon' }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ $isEditing ? 'Make changes to your coupon here. Click save when you\'re done.' : 'Add a new discount coupon customers can redeem at checkout.' }}
            </p>
        </div>

        <form wire:submit="save" class="space-y-4">
            <!-- Code -->
            <x-form-input
                name="code"
                label="Coupon Code"
                type="text"
                wire:model="code"
                placeholder="e.g. FESTIVE20 or FLAT100"
                required
                hint="Unique alphanumeric code cashiers enter during checkout. Converted to uppercase automatically."
            />

            <!-- Discount Mode Switcher (Percentage vs Flat) -->
            <div class="space-y-2">
                <x-input-label for="type" :value="__('Select Discount Mode')" hint="Choose whether this coupon applies a percentage or a flat fixed deduction." />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Flat / Fixed Option -->
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="fixed" wire:model.live="type" class="peer sr-only">
                        <div class="relative flex items-start gap-3 rounded-xl border-2 p-3.5 transition-all text-left peer-checked:border-blue-600 peer-checked:bg-blue-50/80 peer-checked:ring-2 peer-checked:ring-blue-500/20 border-gray-200 bg-white hover:border-gray-300">
                            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-700 shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-gray-900">Flat / Fixed Amount</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-200/70 text-blue-800">Fixed ₹</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">Deducts a fixed currency amount (e.g. ₹100 off total bill).</p>
                            </div>
                        </div>
                    </label>

                    <!-- Percentage Option -->
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="percentage" wire:model.live="type" class="peer sr-only">
                        <div class="relative flex items-start gap-3 rounded-xl border-2 p-3.5 transition-all text-left peer-checked:border-emerald-600 peer-checked:bg-emerald-50/80 peer-checked:ring-2 peer-checked:ring-emerald-500/20 border-gray-200 bg-white hover:border-gray-300">
                            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M17 17h.01M7 17L17 7M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-gray-900">Percentage Mode</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-200/70 text-emerald-800">% OFF</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">Deducts a percentage of cart total with optional maximum cap.</p>
                            </div>
                        </div>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('type')" />
            </div>

            <!-- Separated Mode Fields Container -->
            @if($type === 'percentage')
                <!-- Percentage Mode Panel -->
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4 space-y-3.5">
                    <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-900 uppercase tracking-wider">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Percentage Discount Configuration</span>
                        </div>
                        <span class="text-[11px] text-emerald-700 font-medium">1% to 100%</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-form-input
                            name="value"
                            label="Discount Percentage (%)"
                            type="number"
                            step="1"
                            min="1"
                            max="100"
                            wire:model="value"
                            placeholder="e.g. 15 for 15%"
                            required
                            hint="Percentage rate deducted from cart subtotal (e.g. 10 = 10% off)."
                        />

                        <x-form-input
                            name="max_discount_amount"
                            label="Maximum Discount Cap"
                            type="number"
                            min="0"
                            wire:model="max_discount_amount"
                            placeholder="e.g. 500 (Optional)"
                            hint="Ceiling limit on the discount. Leave blank for no cap."
                        />
                    </div>

                    <div class="text-[11px] text-emerald-800 bg-white/80 rounded-lg p-2.5 border border-emerald-200/80 flex items-start gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span><strong>Example:</strong> A 15% discount on a ₹2,000 subtotal gives ₹300 off. If Maximum Discount Cap is set to ₹200, the customer receives ₹200 off.</span>
                    </div>
                </div>
            @else
                <!-- Flat Amount Panel -->
                <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-4 space-y-3.5">
                    <div class="flex items-center justify-between border-b border-blue-200/60 pb-2">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-blue-900 uppercase tracking-wider">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Flat Discount Configuration</span>
                        </div>
                        <span class="text-[11px] text-blue-700 font-medium">Direct Money Deduction</span>
                    </div>

                    <div>
                        <x-form-input
                            name="value"
                            label="Flat Discount Amount"
                            type="number"
                            step="1"
                            min="1"
                            wire:model="value"
                            placeholder="e.g. 100 for ₹100 flat off"
                            required
                            hint="Exact currency amount deducted from the customer's total bill."
                        />
                    </div>

                    <div class="text-[11px] text-blue-800 bg-white/80 rounded-lg p-2.5 border border-blue-200/80 flex items-start gap-2">
                        <svg class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span><strong>Flat Mode:</strong> The full flat amount is deducted up to the total cart value. No maximum discount cap is needed or used.</span>
                    </div>
                </div>
            @endif

            <!-- Min Purchase and Usage Limit -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-form-input
                    name="min_purchase_amount"
                    label="Minimum Purchase Amount"
                    type="number"
                    min="0"
                    wire:model="min_purchase_amount"
                    placeholder="Optional"
                    hint="Minimum sale subtotal required to use this coupon."
                />

                <x-form-input
                    name="usage_limit"
                    label="Usage Limit"
                    type="number"
                    min="1"
                    wire:model="usage_limit"
                    placeholder="Optional"
                    hint="Maximum number of times this coupon can be redeemed. Leave blank for unlimited."
                />
            </div>

            <!-- Validity Dates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-form-input
                    name="starts_at"
                    label="Starts On"
                    type="date"
                    wire:model="starts_at"
                    hint="Optional date this coupon becomes active."
                />

                <x-form-input
                    name="expires_at"
                    label="Expires On"
                    type="date"
                    wire:model="expires_at"
                    hint="Optional date this coupon stops working."
                />
            </div>

            <!-- Active Status -->
            <div class="flex items-center">
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

            <!-- Description -->
            <div class="space-y-2">
                <x-input-label for="description" :value="__('Description')" hint="Optional notes about this coupon." />
                <textarea
                    id="description"
                    wire:model="description"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Optional description..."
                ></textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-4">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'coupon-form-modal' })">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-primary-button type="submit" wire:loading.attr="disabled">
                    <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <x-heroicon-o-check wire:loading.remove wire:target="save" class="w-4 h-4 mr-2" />
                    {{ $isEditing ? __('Save Changes') : __('Create Coupon') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>
