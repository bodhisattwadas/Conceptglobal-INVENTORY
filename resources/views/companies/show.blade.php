<x-app-layout title="Brand / Company Details">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight">{{ $company->company_name }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $company->short_name ?: __('Brand / Company Details') }}</p>
            </div>
            <a href="{{ route('companies.index') }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                {{ __('Back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $company->company_name }}</h3>
                        <p class="text-sm text-gray-500">{{ $company->company_code ?: '-' }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $company->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-700 ring-slate-200' }}">
                        {{ Str::title($company->status ?: 'active') }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><x-input-label value="{{ __('Brand Name') }}" class="text-muted-foreground" /><p class="text-sm font-medium">{{ $company->short_name ?: '-' }}</p></div>
                    <div><x-input-label value="{{ __('Company Type') }}" class="text-muted-foreground" /><p class="text-sm font-medium">{{ $company->company_type ?: '-' }}</p></div>
                    <div><x-input-label value="{{ __('Suppliers Linked') }}" class="text-muted-foreground" /><p class="text-sm font-medium">{{ $company->suppliers_count ?? 0 }}</p></div>
                    <div><x-input-label value="{{ __('GSTIN') }}" class="text-muted-foreground" /><p class="text-sm font-medium">{{ $company->gstin ?: '-' }}</p></div>
                    <div><x-input-label value="{{ __('PAN') }}" class="text-muted-foreground" /><p class="text-sm font-medium">{{ $company->pan ?: '-' }}</p></div>
                    <div><x-input-label value="{{ __('Phone') }}" class="text-muted-foreground" /><p class="text-sm font-medium">{{ format_indian_phone($company->phone) }}</p></div>
                    <div><x-input-label value="{{ __('Email') }}" class="text-muted-foreground" /><p class="text-sm font-medium">{{ $company->primary_email ?: '-' }}</p></div>
                    <div class="sm:col-span-2">
                        <x-input-label value="{{ __('Address') }}" class="text-muted-foreground" />
                        <p class="text-sm font-medium">{{ collect([$company->address_line_1, $company->address_line_2, $company->city, $company->state, $company->postal_code, $company->country])->filter()->join(', ') ?: '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
