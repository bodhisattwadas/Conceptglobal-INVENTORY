<x-app-layout title="Inventory">
    <x-slot name="header">
        <div class="flex justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Inventory') }}
            </h2>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-adjustment-modal')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <span>{{ __('Adjust / Reallocate Stock') }}</span>
                </button>
                <a
                    href="{{ route('inventory.print') }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 transition-colors"
                >
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>{{ __('Print Report') }}</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div
        class="py-4"
        x-data="{ inventoryTableLoading: false }"
        x-on:inventory-table-loading-start.window="inventoryTableLoading = true"
        x-on:inventory-table-loading-finish.window="inventoryTableLoading = false"
    >
        <div class="relative max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div
                x-cloak
                x-show="inventoryTableLoading"
                x-transition.opacity.duration.150ms
                class="absolute inset-0 z-40 flex items-start justify-center rounded-lg bg-white/70 pt-24 backdrop-blur-[1px]"
            >
                <div class="inline-flex items-center gap-3 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-lg">
                    <svg class="h-5 w-5 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Loading inventory...') }}
                </div>
            </div>
            <livewire:inventory.inventory-table />
        </div>

        <livewire:inventory.stock-adjustment-modal />
    </div>

    @push('scripts')
        <script>
            document.addEventListener('livewire:initialized', () => {
                if (!window.location.pathname.includes('/inventory')) {
                    return;
                }

                Livewire.hook('request', ({ succeed, fail }) => {
                    window.dispatchEvent(new CustomEvent('inventory-table-loading-start'));

                    const finish = () => {
                        requestAnimationFrame(() => {
                            setTimeout(() => {
                                window.dispatchEvent(new CustomEvent('inventory-table-loading-finish'));
                            }, 150);
                        });
                    };

                    succeed(finish);
                    fail(finish);
                });
            });
        </script>
    @endpush

</x-app-layout>
