<x-app-layout title="Products">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Products') }}
            </h2>
            <x-primary-button x-data x-on:click="$dispatch('create-product')">
                <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                {{ __('Create Product') }}
            </x-primary-button>
        </div>
    </x-slot>

    <div
        class="py-4"
        x-data="{ productTableLoading: false }"
        x-on:product-table-loading-start.window="productTableLoading = true"
        x-on:product-table-loading-finish.window="productTableLoading = false"
    >
        <div class="relative max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div
                x-cloak
                x-show="productTableLoading"
                x-transition.opacity.duration.150ms
                class="absolute inset-0 z-40 flex items-start justify-center rounded-lg bg-white/70 pt-24 backdrop-blur-[1px]"
            >
                <div class="inline-flex items-center gap-3 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-lg">
                    <svg class="h-5 w-5 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Loading products...') }}
                </div>
            </div>
            <livewire:products.product-table />
        </div>
    </div>

    <livewire:products.product-form />
    <livewire:products.product-detail />

    @push('scripts')
        <script>
            document.addEventListener('livewire:initialized', () => {
                if (!window.location.pathname.includes('/products')) {
                    return;
                }

                Livewire.hook('request', ({ succeed, fail }) => {
                    window.dispatchEvent(new CustomEvent('product-table-loading-start'));

                    const finish = () => {
                        requestAnimationFrame(() => {
                            setTimeout(() => {
                                window.dispatchEvent(new CustomEvent('product-table-loading-finish'));
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
