<x-app-layout title="Create Purchase Order" header-max-width="max-w-7xl">
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground leading-tight">
                {{ __('Create Purchase Order') }}
            </h2>
            <x-secondary-button href="{{ route('purchases.index') }}">
                &larr; {{ __('Back to List') }}
            </x-secondary-button>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                    <div class="flex items-center gap-2 font-bold text-red-900">
                        <svg class="h-5 w-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ __('Unable to create Purchase Order. Please resolve the following issues:') }}</span>
                    </div>
                    <ul class="mt-2.5 list-disc list-inside space-y-1 font-medium text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($reconIds))
                <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-300 text-amber-900 text-xs flex items-center justify-between shadow-xs">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><strong>{{ __('Reconciliation Mode:') }}</strong> {{ count($reconIds) }} {{ __('item(s) pre-filled from POS manual sales. When this PO is received, those sold units will automatically be reconciled and deducted from stock.') }}</span>
                    </div>
                    <span class="font-mono text-[11px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded border border-amber-300">{{ count($reconIds) }} {{ __('Recon Items') }}</span>
                </div>
            @endif

            <form action="{{ route('purchases.store') }}" method="POST" enctype="multipart/form-data"
                    x-data="purchaseForm({
                        items: {{ Js::from(old('items', $initialItems ?? [])) }},
                        supplier_id: {{ Js::from(old('supplier_id', $initialSupplierId ?? null)) }},
                        company_id: {{ Js::from(old('company_id', $initialCompanyId ?? null)) }},
                        purchase_date: {{ Js::from(old('purchase_date', date('Y-m-d'))) }},
                        due_date: {{ Js::from(old('due_date')) }},
                        location: {{ Js::from(old('location', 'shop')) }},
                        po_reference: {{ Js::from($previewPoReference) }},
                        is_editing: false,
                        status: {{ Js::from(old('status', 'draft')) }},
                        errors: {{ Js::from($errors->any() ? $errors->toArray() : []) }}
                    })"
                    @submit.prevent="submitForm">
                @csrf
                <input type="hidden" name="recon_ids" value="{{ implode(',', $reconIds ?? []) }}">

                @include('purchases.form')

            </form>
        </div>
    </div>
</x-app-layout>
