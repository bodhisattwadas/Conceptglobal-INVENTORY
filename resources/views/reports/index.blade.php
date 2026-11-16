<x-app-layout title="Reports Hub">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-foreground leading-tight flex items-center gap-2">
                    <x-heroicon-o-document-chart-bar class="w-6 h-6 text-primary" />
                    <span>{{ __('Reports Hub') }}</span>
                </h2>
                <p class="text-sm text-muted-foreground mt-0.5">
                    {{ __('Centralized access to business analytics, stock adjustments, and vendor payable aging reports.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Sales Report Card (Admin Only) -->
                @if(Auth::user()->isAdmin())
                <div class="flex flex-col justify-between bg-card text-card-foreground rounded-xl border border-border p-6 shadow-xs hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between pb-4 border-b border-border mb-4">
                            <div class="inline-flex items-center justify-center p-2.5 rounded-lg bg-blue-500/10 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                <x-heroicon-o-chart-bar class="w-6 h-6" />
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                {{ __('Admin Only') }}
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold text-foreground tracking-tight">
                            {{ __('Sales Reports') }}
                        </h3>
                        <p class="text-sm text-muted-foreground mt-2 leading-relaxed">
                            {{ __('Analyze sales volume, gross profit margins, payment method breakdowns, top-selling items, and brand contributions across custom date ranges.') }}
                        </p>
                        <div class="mt-4 space-y-2 text-xs text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Revenue, COGS & net profit tracking') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Exportable to PDF & multi-sheet Excel') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Detailed invoices & daily trend breakdowns') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-border">
                        <a
                            href="{{ route('reports.sales') }}"
                            class="inline-flex items-center justify-center w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-colors"
                        >
                            <span>{{ __('View Sales Report') }}</span>
                            <x-heroicon-o-arrow-right class="ml-2 w-4 h-4" />
                        </a>
                    </div>
                </div>
                @endif

                <!-- Adjustments Report Card -->
                <div class="flex flex-col justify-between bg-card text-card-foreground rounded-xl border border-border p-6 shadow-xs hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between pb-4 border-b border-border mb-4">
                            <div class="inline-flex items-center justify-center p-2.5 rounded-lg bg-amber-500/10 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                <x-heroicon-o-document-chart-bar class="w-6 h-6" />
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                {{ __('Inventory') }}
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold text-foreground tracking-tight">
                            {{ __('Adjustments Report') }}
                        </h3>
                        <p class="text-sm text-muted-foreground mt-2 leading-relaxed">
                            {{ __('Track stock variances, write-offs, surplus additions, and damaged product adjustments with total valuation and reason codes.') }}
                        </p>
                        <div class="mt-4 space-y-2 text-xs text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Write-off & surplus quantity tracking') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Net adjustment valuation impact') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Exportable to PDF & CSV spreadsheets') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-border">
                        <a
                            href="{{ route('reports.adjustments') }}"
                            class="inline-flex items-center justify-center w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-colors"
                        >
                            <span>{{ __('View Adjustments Report') }}</span>
                            <x-heroicon-o-arrow-right class="ml-2 w-4 h-4" />
                        </a>
                    </div>
                </div>

                <!-- Vendor Aging Report Card -->
                <div class="flex flex-col justify-between bg-card text-card-foreground rounded-xl border border-border p-6 shadow-xs hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between pb-4 border-b border-border mb-4">
                            <div class="inline-flex items-center justify-center p-2.5 rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                <x-heroicon-o-clock class="w-6 h-6" />
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                {{ __('Accounts Payable') }}
                            </span>
                        </div>
                        <h3 class="text-lg font-semibold text-foreground tracking-tight">
                            {{ __('Vendor Aging Report') }}
                        </h3>
                        <p class="text-sm text-muted-foreground mt-2 leading-relaxed">
                            {{ __('Monitor outstanding vendor obligations categorized across aging brackets (0–30, 31–60, 61–90, and 90+ days) to optimize payables.') }}
                        </p>
                        <div class="mt-4 space-y-2 text-xs text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Aging bracket distribution breakdown') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Vendor-level outstanding totals & links') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                <span>{{ __('Exportable to landscape PDF & CSV') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-border">
                        <a
                            href="{{ route('reports.vendor-aging') }}"
                            class="inline-flex items-center justify-center w-full rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-colors"
                        >
                            <span>{{ __('View Vendor Aging Report') }}</span>
                            <x-heroicon-o-arrow-right class="ml-2 w-4 h-4" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
