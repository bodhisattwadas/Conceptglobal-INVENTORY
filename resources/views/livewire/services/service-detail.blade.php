<x-modal name="service-detail-modal" :title="''" maxWidth="md">
    <div class="p-6">
        @if($service)
            <div class="flex items-center justify-between border-b border-gray-200 pb-4 mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $service->name }}</h3>
                    <p class="text-xs font-mono text-gray-500 mt-0.5">{{ $service->code ?? 'No Code' }}</p>
                </div>
                <div>
                    @if($service->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Active</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Inactive</span>
                    @endif
                </div>
            </div>

            <div class="space-y-4">
                <div class="bg-indigo-50/60 rounded-xl p-4 border border-indigo-100 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold text-indigo-700 uppercase tracking-wider">Service Cost / Rate</div>
                        <div class="text-2xl font-black text-indigo-900 mt-1">{{ format_money($service->cost) }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold">
                        ₹
                    </div>
                </div>

                @if($service->description)
                    <div>
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Description</div>
                        <div class="text-sm text-gray-700 bg-gray-50 rounded-lg p-3 border border-gray-200">
                            {{ $service->description }}
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4 text-xs text-gray-500 pt-2 border-t border-gray-100">
                    <div>
                        <span class="font-medium text-gray-700">Created:</span>
                        {{ $service->created_at?->format('d M Y, h:i A') }}
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Total Sales:</span>
                        {{ $service->saleItems()->count() }} bookings
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-4">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'service-detail-modal' })">
                    {{ __('Close') }}
                </x-secondary-button>

                <x-primary-button type="button" wire:click="$dispatch('edit-service', { service: {{ $service->id }} })" x-on:click="$dispatch('close-modal', { name: 'service-detail-modal' })">
                    <x-heroicon-o-pencil class="w-4 h-4 mr-1.5" />
                    {{ __('Edit') }}
                </x-primary-button>
            </div>
        @endif
    </div>
</x-modal>
