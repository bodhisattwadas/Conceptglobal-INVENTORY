<x-modal name="product-hsn-import-modal" :title="''" maxWidth="2xl" :close-on-outside="false" :close-on-escape="false">
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
            <div class="space-y-1 text-left">
                <h3 class="text-lg font-semibold text-foreground flex items-center gap-2">
                    <x-heroicon-o-arrow-up-tray class="w-5 h-5 text-indigo-600" />
                    {{ __('Import & Sync HSN Codes') }}
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ __('Download your existing product list, fill in the HSN codes, and upload to bulk-update.') }}
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                x-on:click="$dispatch('close-modal', { name: 'product-hsn-import-modal' })"
                aria-label="{{ __('Close') }}"
            >
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
        </div>

        @if($importSummary)
            <!-- Results Summary Card -->
            <div class="space-y-6">
                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $importSummary['updated_count'] > 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-blue-100 text-blue-600' }}">
                            <x-heroicon-o-check-circle class="h-6 w-6" />
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-gray-900">
                                {{ __('Synchronization Complete') }}
                            </h4>
                            <p class="text-xs text-gray-500">
                                {{ __('Your product catalog has been updated with the imported HSN codes.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Metric Badges Grid -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 pt-2">
                        <div class="rounded-lg border border-gray-200 bg-white p-3 text-center">
                            <span class="text-xs font-medium text-gray-500 block uppercase tracking-wider">{{ __('Total Rows') }}</span>
                            <span class="text-xl font-bold text-gray-800">{{ number_format($importSummary['total_rows']) }}</span>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 text-center">
                            <span class="text-xs font-semibold text-emerald-700 block uppercase tracking-wider">{{ __('Updated') }}</span>
                            <span class="text-xl font-bold text-emerald-700">{{ number_format($importSummary['updated_count']) }}</span>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-3 text-center">
                            <span class="text-xs font-medium text-gray-500 block uppercase tracking-wider">{{ __('Unchanged') }}</span>
                            <span class="text-xl font-bold text-gray-600">{{ number_format($importSummary['unchanged_count']) }}</span>
                        </div>
                        <div class="rounded-lg border {{ $importSummary['failed_count'] > 0 ? 'border-amber-300 bg-amber-50/60 text-amber-800' : 'border-gray-200 bg-white text-gray-400' }} p-3 text-center">
                            <span class="text-xs font-medium block uppercase tracking-wider">{{ __('Unmatched') }}</span>
                            <span class="text-xl font-bold">{{ number_format($importSummary['failed_count']) }}</span>
                        </div>
                    </div>
                </div>

                @if(!empty($importSummary['errors']))
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 space-y-2 text-xs text-amber-800">
                        <div class="font-semibold flex items-center gap-1.5">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-600" />
                            <span>{{ __('Unmatched Rows / Warnings:') }}</span>
                        </div>
                        <ul class="list-disc pl-5 space-y-1 max-h-36 overflow-y-auto font-mono text-[11px]">
                            @foreach($importSummary['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        @if($importSummary['has_more_errors'])
                            <p class="text-[11px] italic text-amber-700 mt-1">
                                {{ __('...and more unmatched rows omitted from preview.') }}
                            </p>
                        @endif
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200">
                    <x-secondary-button type="button" wire:click="resetSummary">
                        {{ __('Sync Another File') }}
                    </x-secondary-button>
                    <x-primary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-hsn-import-modal' })">
                        {{ __('Close') }}
                    </x-primary-button>
                </div>
            </div>
        @else
            <!-- Step by Step Workflow Form -->
            <div class="space-y-6">

                <!-- Step 1: Download Section -->
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 sm:p-5 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">
                            1
                        </div>
                        <div class="flex-1 space-y-1">
                            <h4 class="text-sm font-semibold text-gray-900">
                                {{ __('Download Products Spreadsheet (.xlsx)') }}
                            </h4>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                {{ __('The spreadsheet includes all existing products pre-filled with Product Code, Name, SKU, and existing HSN Code.') }}
                            </p>
                        </div>
                    </div>

                    <div class="pt-2 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <a
                            href="{{ route('products.hsn-template') }}"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors"
                        >
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                            {{ __('Download All Products (.xlsx)') }}
                        </a>

                        <a
                            href="{{ route('products.hsn-template', ['sample' => 1]) }}"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                        >
                            <x-heroicon-o-document-text class="w-4 h-4" />
                            {{ __('Download Blank Sample (.xlsx)') }}
                        </a>
                    </div>

                    <!-- Column Layout Preview -->
                    <div class="pt-2">
                        <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider mb-1.5">{{ __('Expected Header Format:') }}</p>
                        <div class="overflow-x-auto rounded border border-gray-200 bg-white">
                            <table class="w-full text-left text-xs font-mono">
                                <thead>
                                    <tr class="bg-gray-100/80 text-gray-700 border-b border-gray-200">
                                        <th class="px-3 py-1.5 font-semibold">Product Code</th>
                                        <th class="px-3 py-1.5 font-semibold text-indigo-700">HSN Code</th>
                                        <th class="px-3 py-1.5 font-semibold text-gray-500">Product Name</th>
                                        <th class="px-3 py-1.5 font-semibold text-gray-500">SKU</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="text-gray-600">
                                        <td class="px-3 py-1.5">8906036215017</td>
                                        <td class="px-3 py-1.5 font-bold text-indigo-600">33049900</td>
                                        <td class="px-3 py-1.5 text-gray-400">Strawberry Body Wash</td>
                                        <td class="px-3 py-1.5 text-gray-400">P.260101.ABCD</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Upload & Sync Section -->
                <form wire:submit.prevent="import" class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">
                            2
                        </div>
                        <div class="flex-1 space-y-1">
                            <h4 class="text-sm font-semibold text-gray-900">
                                {{ __('Upload Updated Spreadsheet & Sync') }}
                            </h4>
                            <p class="text-xs text-muted-foreground leading-relaxed">
                                {{ __('Select the completed file (.xlsx or .csv) to synchronize HSN codes in the database.') }}
                            </p>
                        </div>
                    </div>

                    <!-- File Input / Drop Box -->
                    <div class="pl-10">
                        <div class="relative rounded-lg border-2 border-dashed border-gray-300 p-5 text-center hover:border-indigo-400 transition-colors bg-gray-50/30">
                            <input
                                id="hsn_import_file"
                                type="file"
                                wire:model="file"
                                accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            >
                            <div class="space-y-1.5">
                                <x-heroicon-o-cloud-arrow-up class="mx-auto h-8 w-8 text-gray-400" />
                                <div class="text-xs text-gray-600">
                                    <span class="font-semibold text-indigo-600 hover:text-indigo-500">{{ __('Click to browse') }}</span>
                                    <span>{{ __('or drag and drop your spreadsheet here') }}</span>
                                </div>
                                <p class="text-[11px] text-gray-400">{{ __('Supported: .XLSX or .CSV (max 15 MB)') }}</p>
                            </div>
                        </div>

                        <!-- Uploaded File Indicator -->
                        <div wire:loading wire:target="file" class="mt-2 text-xs text-indigo-600 font-medium flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('Uploading spreadsheet...') }}
                        </div>

                        @if($file)
                            <div wire:loading.remove wire:target="file" class="mt-2 flex items-center justify-between rounded-md border border-gray-200 bg-white px-3 py-2 text-xs">
                                <div class="flex items-center gap-2 text-gray-700 truncate">
                                    <x-heroicon-o-document-text class="w-4 h-4 text-emerald-600 shrink-0" />
                                    <span class="font-medium truncate">{{ $file->getClientOriginalName() }}</span>
                                </div>
                                <button type="button" wire:click="$set('file', null)" class="text-gray-400 hover:text-rose-600 ml-2" title="{{ __('Remove file') }}">
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                </button>
                            </div>
                        @endif

                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    <!-- Modal Action Buttons -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-hsn-import-modal' })">
                            {{ __('Cancel') }}
                        </x-secondary-button>

                        <x-primary-button type="submit" wire:loading.attr="disabled" :disabled="!$file">
                            <svg wire:loading wire:target="import" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <x-heroicon-o-arrow-path wire:loading.remove wire:target="import" class="w-4 h-4 mr-2" />
                            {{ __('Sync HSN Codes') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-modal>
