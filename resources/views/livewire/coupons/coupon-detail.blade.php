<x-modal name="coupon-detail-modal" focusable>
    @if($coupon)
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6 space-y-1.5 text-center sm:text-left border-b border-gray-200 pb-4">
                <h3 class="text-lg font-semibold leading-none tracking-tight text-foreground">
                    {{ __('Coupon Details') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ __('Detailed information about') }} {{ $coupon->code }}.
                </p>
            </div>

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Code') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->code }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Status') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Discount') }}</label>
                        <p class="text-sm text-foreground font-medium">
                            {{ $coupon->type->value === 'percentage' ? $coupon->value . '%' : format_money($coupon->value) }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Max Discount') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->max_discount_amount ? format_money($coupon->max_discount_amount) : '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Minimum Purchase') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->min_purchase_amount ? format_money($coupon->min_purchase_amount) : '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Usage') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->used_count }} / {{ $coupon->usage_limit ?? '∞' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Starts On') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->starts_at?->format('d M Y') ?? '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Expires On') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->expires_at?->format('d M Y') ?? '-' }}</p>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Description') }}</label>
                    <p class="text-sm text-foreground font-medium">
                        {{ $coupon->description ?? '-' }}
                    </p>
                </div>

                <!-- Meta -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Created At') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->created_at?->format('d M Y, H:i') ?? '-' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Last Updated') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->updated_at?->format('d M Y, H:i') ?? '-' }}</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex items-center justify-end gap-x-2 pt-4 border-t border-border">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'coupon-detail-modal' })">
                    {{ __('Close') }}
                </x-secondary-button>
                <x-primary-button type="button" x-on:click="$dispatch('close-modal', { name: 'coupon-detail-modal' }); $dispatch('edit-coupon', { coupon: {{ $coupon->id }} })">
                    <x-heroicon-o-pencil-square class="w-4 h-4 mr-2" />
                    {{ __('Edit Coupon') }}
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
