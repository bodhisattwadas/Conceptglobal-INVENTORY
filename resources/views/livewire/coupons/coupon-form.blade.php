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
                placeholder="e.g. WELCOME10"
                required
                hint="Unique code customers enter to redeem the discount. Example: WELCOME10."
            />

            <!-- Type -->
            <div class="space-y-3">
                <x-input-label for="type" :value="__('Discount Type')" hint="Whether the discount is a percentage or a fixed amount." />
                <div class="grid grid-cols-2 gap-4">
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="fixed" wire:model="type" class="peer sr-only">
                        <div class="relative flex items-center justify-center gap-2 rounded-lg border border-input bg-background px-4 py-2.5 text-center transition-all hover:bg-accent hover:text-accent-foreground peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700 peer-checked:ring-1 peer-checked:ring-blue-500">
                            <span class="text-sm font-medium">Fixed Amount</span>
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="percentage" wire:model="type" class="peer sr-only">
                        <div class="relative flex items-center justify-center gap-2 rounded-lg border border-input bg-background px-4 py-2.5 text-center transition-all hover:bg-accent hover:text-accent-foreground peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 peer-checked:ring-1 peer-checked:ring-emerald-500">
                            <span class="text-sm font-medium">Percentage</span>
                        </div>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('type')" />
            </div>

            <!-- Value and Max Discount -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-form-input
                    name="value"
                    :label="$type === 'percentage' ? 'Percentage (%)' : 'Discount Amount'"
                    type="number"
                    step="0.01"
                    min="0"
                    wire:model="value"
                    placeholder="0"
                    required
                    hint="{{ $type === 'percentage' ? 'Percent off the sale subtotal. Example: 10.' : 'Flat amount deducted from the sale. Example: 5000.' }}"
                />

                <x-form-input
                    name="max_discount_amount"
                    label="Max Discount Amount"
                    type="number"
                    min="0"
                    wire:model="max_discount_amount"
                    placeholder="Optional"
                    hint="Caps the discount value for percentage coupons. Leave blank for no cap."
                />
            </div>

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
