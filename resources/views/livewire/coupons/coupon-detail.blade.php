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
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Coupon Code') }}</label>
                        <p class="text-base text-foreground font-mono font-bold">{{ $coupon->code }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Discount Mode') }}</label>
                        <div>
                            {!! $coupon->type->badgeHtml() !!}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Discount Value') }}</label>
                        <p class="text-sm font-bold {{ $coupon->type->isPercentage() ? 'text-emerald-700' : 'text-blue-700' }}">
                            {{ $coupon->type->isPercentage() ? $coupon->value . '% OFF' : format_money($coupon->value) . ' Flat OFF' }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Max Discount Cap') }}</label>
                        <p class="text-sm text-foreground">
                            @if($coupon->type->isPercentage())
                                {{ $coupon->max_discount_amount ? format_money($coupon->max_discount_amount) : 'Unlimited (No cap)' }}
                            @else
                                <span class="text-gray-400 italic">Not applicable (Flat discount)</span>
                            @endif
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Minimum Cart Purchase') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->min_purchase_amount ? format_money($coupon->min_purchase_amount) : 'None (Any cart value)' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Usage Limit') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->used_count }} / {{ $coupon->usage_limit ?? '∞ (Unlimited)' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Valid From') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->starts_at?->format('d M Y') ?? 'Immediate' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium leading-none text-muted-foreground">{{ __('Expires On') }}</label>
                        <p class="text-sm text-foreground font-medium">{{ $coupon->expires_at?->format('d M Y') ?? 'No expiration' }}</p>
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
