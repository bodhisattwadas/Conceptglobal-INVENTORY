<div class="space-y-6">
    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="p-4 mb-4 text-sm text-emerald-800 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">&times;</button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-rose-800 rounded-lg bg-rose-50 dark:bg-rose-900/30 dark:text-rose-300 border border-rose-200 dark:border-rose-800 flex items-center justify-between">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2 text-rose-600 dark:text-rose-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-600 hover:text-rose-800">&times;</button>
        </div>
    @endif

    <!-- Register Navigation Tabs -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-border pb-4">
        <div class="flex space-x-2">
            <button
                type="button"
                wire:click="$set('activeTab', 'cash')"
                class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors flex items-center gap-2 {{ $activeTab === 'cash' ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-muted text-muted-foreground hover:bg-muted/80' }}"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Cash Register</span>
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'bank')"
                class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors flex items-center gap-2 {{ $activeTab === 'bank' ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-muted text-muted-foreground hover:bg-muted/80' }}"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                </svg>
                <span>Bank Register</span>
            </button>
        </div>

        <!-- Period Selectors: Month & Year -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <label for="month-select" class="text-xs font-medium text-muted-foreground">Month:</label>
                <select
                    id="month-select"
                    wire:model.live="selectedMonth"
                    class="rounded-lg border border-input bg-background px-3 py-1.5 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                >
                    @foreach ($months as $num => $name)
                        <option value="{{ $num }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label for="year-select" class="text-xs font-medium text-muted-foreground">Year:</label>
                <select
                    id="year-select"
                    wire:model.live="selectedYear"
                    class="rounded-lg border border-input bg-background px-3 py-1.5 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                >
                    @foreach ($years as $yr)
                        <option value="{{ $yr }}">{{ $yr }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Monthly Summary KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-card text-card-foreground p-5 rounded-xl border border-border shadow-sm">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Beginning Balance</div>
            <div class="mt-2 text-2xl font-bold tracking-tight text-foreground">
                {{ number_format($summary['beginning_balance'], 2) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">Opening balance as of 1st of month</div>
        </div>

        <div class="bg-card text-card-foreground p-5 rounded-xl border border-border shadow-sm">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Inflows</div>
            <div class="mt-2 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                +{{ number_format($summary['total_inflows'], 2) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">Sales &amp; capital investments</div>
        </div>

        <div class="bg-card text-card-foreground p-5 rounded-xl border border-border shadow-sm">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Outflows</div>
            <div class="mt-2 text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                -{{ number_format($summary['total_outflows'], 2) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">Expenses &amp; owner drawings</div>
        </div>

        <div class="bg-card text-card-foreground p-5 rounded-xl border border-border shadow-sm bg-gradient-to-br from-card to-muted/20">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">System Recorded Balance</div>
            <div class="mt-2 text-2xl font-bold tracking-tight text-primary">
                {{ number_format($recordedBalance, 2) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">Month-end computed ledger ending balance</div>
        </div>
    </div>

    <!-- Reconciliation Entry Panel -->
    <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 mb-6 border-b border-border">
            <div>
                <h3 class="text-lg font-semibold text-foreground">
                    Month-End {{ $type->label() }} Reconciliation for {{ $months[$selectedMonth] ?? (\Carbon\Carbon::createFromDate($selectedYear, max(1, min(12, (int) $selectedMonth)), 1)->format('F')) }} {{ $selectedYear }}
                </h3>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Compare actual closing physical balance or statement balance against system records to identify variances.
                </p>
            </div>

            <!-- Reactive Status Badge -->
            <div class="mt-3 sm:mt-0">
                @if ($status !== null)
                    @if ($status === \App\Enums\ReconciliationStatus::Reconciled)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                            <svg class="w-3.5 h-3.5 mr-1 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            Reconciled
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                            <svg class="w-3.5 h-3.5 mr-1 text-amber-600 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            Discrepancy
                        </span>
                    @endif
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground border border-border">
                        Pending Entry
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column: Actual Balance Input & Variance -->
            <div class="space-y-4">
                <div>
                    <label for="actualBalance" class="block text-sm font-medium text-foreground">
                        {{ $activeTab === 'cash' ? 'Actual Physical Cash Count (₹)' : 'Actual Bank Statement Balance (₹)' }}
                        <span class="text-rose-500">*</span>
                    </label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted-foreground text-sm font-medium">
                            ₹
                        </div>
                        <input
                            type="number"
                            step="0.01"
                            id="actualBalance"
                            wire:model.live="actualBalance"
                            placeholder="0.00"
                            class="block w-full pl-8 pr-12 rounded-lg border border-input bg-background py-2 text-foreground text-base font-semibold shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary sm:text-sm"
                        />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-muted-foreground text-xs">
                            INR
                        </div>
                    </div>
                    @error('actualBalance')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Live Variance / Difference Display -->
                <div class="p-4 rounded-lg border {{ $difference !== null ? (abs($difference) < 0.001 ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800') : 'bg-muted/50 border-border' }}">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Variance / Difference</span>
                        <span class="text-xs font-semibold {{ $difference !== null ? (abs($difference) < 0.001 ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300') : 'text-muted-foreground' }}">
                            {{ $difference !== null ? (abs($difference) < 0.001 ? '✓ Balanced (Zero Diff)' : ($difference > 0 ? 'Surplus (+)' : 'Shortage (-)')) : 'Awaiting input' }}
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold tracking-tight {{ $difference !== null ? (abs($difference) < 0.001 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400') : 'text-muted-foreground' }}">
                        {{ $difference !== null ? ($difference >= 0 ? '+' : '').number_format($difference, 2) : '0.00' }}
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Calculation: Actual Entered ({{ $actualBalance !== null ? number_format((float)$actualBalance, 2) : '0.00' }}) - Recorded ({{ number_format($recordedBalance, 2) }})
                    </p>
                </div>

                @if ($activeTab === 'cash')
                    <!-- Optional Cash Denomination Breakdown -->
                    <div x-data="{ openDenominations: false }" class="border border-border rounded-lg p-3 bg-muted/20">
                        <button
                            type="button"
                            @click="openDenominations = !openDenominations"
                            class="w-full flex justify-between items-center text-xs font-semibold text-muted-foreground hover:text-foreground"
                        >
                            <span>Physical Cash Currency Denomination Breakdown (Optional)</span>
                            <svg class="w-4 h-4 transform transition-transform" :class="{'rotate-180': openDenominations}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="openDenominations" x-collapse class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-border">
                            @foreach (['500', '200', '100', '50', '20', '10'] as $note)
                                <div>
                                    <label class="text-[11px] font-medium text-muted-foreground">₹{{ $note }} count:</label>
                                    <input
                                        type="number"
                                        min="0"
                                        wire:model.live="denominations.{{ $note }}"
                                        class="w-full text-xs rounded border-input bg-background p-1"
                                    />
                                </div>
                            @endforeach
                            <div>
                                <label class="text-[11px] font-medium text-muted-foreground">Coins total (₹):</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model.live="denominations.coins"
                                    class="w-full text-xs rounded border-input bg-background p-1"
                                />
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: Notes & Submission -->
            <div class="space-y-4 flex flex-col justify-between">
                <div>
                    <label for="notes" class="block text-sm font-medium text-foreground">
                        Reconciliation Notes &amp; Variance Explanations
                    </label>
                    <p class="text-xs text-muted-foreground mb-1">
                        Explain any discrepancies, unpresented cheques, pending deposits, bank charges, or physical cash shortages/surpluses.
                    </p>
                    <textarea
                        id="notes"
                        rows="5"
                        wire:model="notes"
                        placeholder="e.g. Physical cash count matched perfectly / Shortage identified in drawer 2 / Unpresented cheque #104 pending clearance..."
                        class="block w-full rounded-lg border border-input bg-background p-2.5 text-sm text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    ></textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Action Button -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                    <button
                        type="button"
                        wire:click="saveReconciliation"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary shadow-sm transition-colors disabled:opacity-50"
                    >
                        <svg wire:loading class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Save Reconciliation Record</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconciliation History Table -->
    <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="p-5 border-b border-border">
            <h3 class="text-base font-semibold text-foreground">Reconciliation History &amp; Audit Trail</h3>
            <p class="text-xs text-muted-foreground mt-0.5">
                Past month-end reconciliations across Cash and Bank registers with manager review status.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-foreground">
                <thead class="bg-muted/50 text-xs font-semibold text-muted-foreground uppercase tracking-wider border-b border-border">
                    <tr>
                        <th class="px-4 py-3">Register</th>
                        <th class="px-4 py-3">Period</th>
                        <th class="px-4 py-3">Reconciliation Date</th>
                        <th class="px-4 py-3 text-right">Recorded Balance</th>
                        <th class="px-4 py-3 text-right">Actual Balance</th>
                        <th class="px-4 py-3 text-right">Difference</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3">Reconciled By</th>
                        <th class="px-4 py-3">Reviewed By</th>
                        <th class="px-4 py-3">Notes</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($history as $record)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 font-medium whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $record->register_type === \App\Enums\RegisterType::Cash ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' }}">
                                    {{ $record->register_type->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium whitespace-nowrap">
                                {{ \Carbon\Carbon::createFromDate($record->year, $record->month, 1)->format('M Y') }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground whitespace-nowrap">
                                {{ $record->reconciliation_date?->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                {{ number_format((float) $record->recorded_balance, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">
                                {{ number_format((float) $record->actual_balance, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap {{ abs((float) $record->difference) < 0.001 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ (float) $record->difference >= 0 ? '+' : '' }}{{ number_format((float) $record->difference, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $record->status->badgeColor() }}">
                                    {{ $record->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">
                                <div>{{ $record->reconciler?->name ?? 'User #'.$record->reconciled_by }}</div>
                                <div class="text-[10px] text-muted-foreground/70">{{ $record->reconciled_at?->format('d M Y H:i') }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                @if ($record->reviewed_by)
                                    <div class="text-emerald-600 dark:text-emerald-400 font-medium">{{ $record->reviewer?->name ?? 'User #'.$record->reviewed_by }}</div>
                                    <div class="text-[10px] text-muted-foreground/70">{{ $record->reviewed_at?->format('d M Y H:i') }}</div>
                                @else
                                    <span class="text-xs text-muted-foreground/70 italic">Pending Review</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground max-w-xs truncate" title="{{ $record->notes }}">
                                {{ $record->notes ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if (! $record->reviewed_by && auth()->check() && (auth()->user()->isAdmin() || auth()->user()->hasRole('manager')))
                                    <button
                                        type="button"
                                        wire:click="review({{ $record->id }})"
                                        class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded bg-muted hover:bg-muted/80 text-foreground transition-colors"
                                    >
                                        Approve
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                No past reconciliation records found. Select a register and period above to perform reconciliation.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
