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

    <!-- Top KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-card text-card-foreground p-5 rounded-xl border border-border shadow-sm">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Beginning Balance</div>
            <div class="mt-2 text-2xl font-bold tracking-tight text-foreground">
                {{ number_format($beginningBalance, 2) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">Starting bank baseline for active period</div>
        </div>

        <div class="bg-card text-card-foreground p-5 rounded-xl border border-border shadow-sm">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Bank Inflows</div>
            <div class="mt-2 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                +{{ number_format($totalInflows, 2) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">UPI, Card, Transfers &amp; Investments</div>
        </div>

        <div class="bg-card text-card-foreground p-5 rounded-xl border border-border shadow-sm">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Bank Outflows</div>
            <div class="mt-2 text-2xl font-bold tracking-tight text-rose-600 dark:text-rose-400">
                -{{ number_format($totalOutflows, 2) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">Cheque expenses &amp; owner drawings</div>
        </div>

        <div class="bg-card text-card-foreground p-5 rounded-xl border border-border shadow-sm">
            <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Net Bank Balance</div>
            <div class="mt-2 text-2xl font-bold tracking-tight {{ $endingBalance >= 0 ? 'text-primary' : 'text-rose-600 dark:text-rose-400' }}">
                {{ number_format($endingBalance, 2) }}
            </div>
            <div class="mt-1 text-xs text-muted-foreground">Current available bank balance</div>
        </div>
    </div>

    <!-- Action Bar & Modals Trigger -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-card p-4 rounded-xl border border-border shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="openInvestmentModal"
                class="inline-flex items-center px-3.5 py-2 text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm transition-colors"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                + Bank Investment
            </button>

            <button
                type="button"
                wire:click="openExpenseModal"
                class="inline-flex items-center px-3.5 py-2 text-sm font-semibold rounded-lg bg-rose-600 text-white hover:bg-rose-700 shadow-sm transition-colors"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                </svg>
                - Cheque Expense
            </button>

            <button
                type="button"
                wire:click="openDrawingModal"
                class="inline-flex items-center px-3.5 py-2 text-sm font-semibold rounded-lg bg-amber-600 text-white hover:bg-amber-700 shadow-sm transition-colors"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                - Cheque Drawing
            </button>

            <button
                type="button"
                wire:click="openBeginningBalanceModal"
                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-border bg-background text-foreground hover:bg-accent transition-colors"
            >
                <svg class="w-4 h-4 mr-1.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Beginning Balance
            </button>
        </div>

        <!-- Filter Controls -->
        <div class="flex flex-wrap items-center gap-2">
            <select
                wire:model.live="dateFilter"
                class="text-sm rounded-lg border-border bg-background text-foreground py-1.5 px-3 focus:ring-primary focus:border-primary"
            >
                <option value="all_time">All Time</option>
                <option value="today">Today</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="this_quarter">This Quarter</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom Range</option>
            </select>

            @if ($dateFilter === 'custom')
                <input
                    type="date"
                    wire:model.live="startDate"
                    class="text-sm rounded-lg border-border bg-background text-foreground py-1.5 px-2 focus:ring-primary focus:border-primary"
                />
                <span class="text-muted-foreground text-xs">to</span>
                <input
                    type="date"
                    wire:model.live="endDate"
                    class="text-sm rounded-lg border-border bg-background text-foreground py-1.5 px-2 focus:ring-primary focus:border-primary"
                />
            @endif

            <select
                wire:model.live="typeFilter"
                class="text-sm rounded-lg border-border bg-background text-foreground py-1.5 px-3 focus:ring-primary focus:border-primary"
            >
                <option value="all">All Types</option>
                <option value="bank_sale">Bank Sales</option>
                <option value="upi_sale">UPI Sales</option>
                <option value="card_sale">Card Sales</option>
                <option value="transfer_sale">Bank Transfers</option>
                <option value="bank_investment">Bank Investments</option>
                <option value="expense">Cheque Expenses</option>
                <option value="drawing">Cheque Drawings</option>
            </select>

            <div class="relative min-w-[200px]">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search cheque, num, name, memo..."
                    class="w-full text-sm rounded-lg border-border bg-background text-foreground pl-8 pr-3 py-1.5 focus:ring-primary focus:border-primary"
                />
                <svg class="w-4 h-4 text-muted-foreground absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Ledger Table matching Reference Layout -->
    <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm overflow-hidden">
        <!-- Hierarchical Category Header -->
        <div class="bg-muted/70 px-4 py-2.5 border-b border-border flex items-center justify-between cursor-pointer select-none" wire:click="toggleCollapse">
            <div class="flex items-center space-x-2 font-bold text-sm text-foreground">
                <span class="transform transition-transform {{ $isCollapsed ? '-rotate-90' : 'rotate-0' }}">
                    <svg class="w-4 h-4 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </span>
                <span>1200 Bank Account</span>
                <span class="text-xs font-normal text-muted-foreground">({{ $totalCount }} {{ \Illuminate\Support\Str::plural('transaction', $totalCount) }})</span>
            </div>
            <div class="text-xs font-semibold text-muted-foreground">
                Net Period Flow: <span class="{{ ($totalInflows - $totalOutflows) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    {{ ($totalInflows - $totalOutflows) >= 0 ? '+' : '' }}{{ number_format($totalInflows - $totalOutflows, 2) }}
                </span>
            </div>
        </div>

        @if (! $isCollapsed)
            <!-- Hierarchical Subaccount Header -->
            <div class="bg-muted/30 px-6 py-2 border-b border-border flex items-center space-x-2 text-xs font-semibold text-muted-foreground">
                <svg class="w-3.5 h-3.5 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
                <span>1201 Primary Business Account ({{ $totalCount }})</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border bg-muted/40 font-semibold text-muted-foreground">
                            <th class="py-2.5 px-3 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1">
                                    Transaction date
                                    <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                </span>
                            </th>
                            <th class="py-2.5 px-3 whitespace-nowrap">Transaction type</th>
                            <th class="py-2.5 px-3 whitespace-nowrap">Num</th>
                            <th class="py-2.5 px-3 whitespace-nowrap">Name</th>
                            <th class="py-2.5 px-3 whitespace-nowrap">Description</th>
                            <th class="py-2.5 px-3 whitespace-nowrap">Account Name</th>
                            <th class="py-2.5 px-3 whitespace-nowrap">Item split account</th>
                            <th class="py-2.5 px-3 text-right whitespace-nowrap">Amount</th>
                            <th class="py-2.5 px-3 text-right whitespace-nowrap">Balance</th>
                            <th class="py-2.5 px-3 text-center whitespace-nowrap">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border font-normal text-foreground">
                        <!-- Beginning Balance Row -->
                        <tr class="bg-muted/10 font-medium">
                            <td class="py-2 px-3 text-muted-foreground whitespace-nowrap">
                                {{ $startDate ? rescue(fn() => \Carbon\Carbon::parse($startDate)->format('m/d/Y'), '-') : '-' }}
                            </td>
                            <td class="py-2 px-3 whitespace-nowrap">
                                <span class="text-xs font-semibold text-muted-foreground">Beginning Balance</span>
                            </td>
                            <td class="py-2 px-3 text-muted-foreground">-</td>
                            <td class="py-2 px-3 font-semibold text-foreground">Beginning Balance</td>
                            <td class="py-2 px-3 text-muted-foreground">Opening baseline bank balance</td>
                            <td class="py-2 px-3 text-muted-foreground">1200 Bank Account</td>
                            <td class="py-2 px-3 text-muted-foreground">-</td>
                            <td class="py-2 px-3 text-right text-muted-foreground">-</td>
                            <td class="py-2 px-3 text-right font-bold text-foreground whitespace-nowrap">
                                {{ number_format($beginningBalance, 2) }}
                            </td>
                            <td class="py-2 px-3 text-center text-muted-foreground">-</td>
                        </tr>

                        <!-- Transaction Rows -->
                        @forelse ($entries as $txn)
                            <tr class="hover:bg-muted/20 transition-colors">
                                <td class="py-2 px-3 whitespace-nowrap">
                                    {{ $txn->transaction_date ? \Carbon\Carbon::parse($txn->transaction_date)->format('m/d/Y') : '-' }}
                                </td>
                                <td class="py-2 px-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium {{ $txn->transaction_type->badgeColor() }}">
                                        {{ $txn->transaction_type->label() }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 whitespace-nowrap font-mono text-[11px]">
                                    {{ $txn->reference_number ?: '-' }}
                                </td>
                                <td class="py-2 px-3 max-w-[180px] truncate" title="{{ $txn->name }}">
                                    {{ $txn->name ?: '-' }}
                                </td>
                                <td class="py-2 px-3 max-w-[220px] truncate" title="{{ $txn->description }}">
                                    {{ $txn->description ?: '-' }}
                                </td>
                                <td class="py-2 px-3 whitespace-nowrap text-muted-foreground">
                                    {{ $txn->account_name }}
                                </td>
                                <td class="py-2 px-3 whitespace-nowrap text-muted-foreground">
                                    {{ $txn->split_account ?: ($txn->category?->name ?: '-') }}
                                </td>
                                <td class="py-2 px-3 text-right whitespace-nowrap font-medium {{ $txn->transaction_type->isOutflow() ? 'text-rose-600 dark:text-rose-400' : 'text-foreground' }}">
                                    {{ $txn->transaction_type->isOutflow() ? '-' : '' }}{{ number_format($txn->amount, 2) }}
                                </td>
                                <td class="py-2 px-3 text-right whitespace-nowrap font-semibold {{ $txn->running_balance < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground' }}">
                                    {{ number_format($txn->running_balance, 2) }}
                                </td>
                                <td class="py-2 px-3 text-center whitespace-nowrap">
                                    @if ($txn->reference_type === null)
                                        <div class="inline-flex items-center gap-1">
                                            <button
                                                type="button"
                                                wire:click="editTransaction({{ $txn->id }})"
                                                class="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50 dark:hover:bg-blue-950 transition-colors"
                                                title="Edit Transaction"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="deleteTransaction({{ $txn->id }})"
                                                wire:confirm="Are you sure you want to delete this bank transaction?"
                                                class="text-rose-600 hover:text-rose-800 p-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950 transition-colors"
                                                title="Delete Transaction"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center text-muted-foreground" title="Automated from Sale {{ $txn->reference_number }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-muted-foreground text-sm">
                                    No bank transactions found for the selected period and criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Modals Section -->

    <!-- 1. Bank Investment Modal -->
    @if ($showInvestmentModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
            <div class="bg-card text-card-foreground rounded-xl max-w-md w-full border border-border shadow-xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-foreground">Record Bank Investment</h3>
                    <button type="button" wire:click="$set('showInvestmentModal', false)" class="text-muted-foreground hover:text-foreground">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Transaction Date *</label>
                        <input type="date" wire:model="inv_date" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('inv_date') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Amount *</label>
                        <input type="number" step="0.01" min="0.01" wire:model="inv_amount" placeholder="0.00" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('inv_amount') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Investor / Contributor Name</label>
                        <input type="text" wire:model="inv_name" placeholder="e.g. Owner Capital / Tech Ventures" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('inv_name') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Reference Number (Num / Deposit Ref)</label>
                        <input type="text" wire:model="inv_num" placeholder="e.g. DEP-BANK-1" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('inv_num') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Item Split Account</label>
                        <input type="text" wire:model="inv_split" placeholder="Owner Investment / Capital" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('inv_split') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Description / Memo</label>
                        <textarea wire:model="inv_description" rows="2" placeholder="Additional investment details..." class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3"></textarea>
                        @error('inv_description') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-border pt-4">
                    <button type="button" wire:click="$set('showInvestmentModal', false)" class="px-4 py-2 text-sm rounded-lg border border-border bg-background hover:bg-accent">Cancel</button>
                    <button type="button" wire:click="saveInvestment" class="px-4 py-2 text-sm rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700">Record Investment</button>
                </div>
            </div>
        </div>
    @endif

    <!-- 2. Cheque Expense Modal -->
    @if ($showExpenseModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
            <div class="bg-card text-card-foreground rounded-xl max-w-md w-full border border-border shadow-xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-foreground">Record Cheque Expense</h3>
                    <button type="button" wire:click="$set('showExpenseModal', false)" class="text-muted-foreground hover:text-foreground">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Transaction Date *</label>
                        <input type="date" wire:model="exp_date" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('exp_date') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Amount *</label>
                        <input type="number" step="0.01" min="0.01" wire:model="exp_amount" placeholder="0.00" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('exp_amount') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Payee Name</label>
                        <input type="text" wire:model="exp_name" placeholder="e.g. Commercial Landlord / Vendor Ltd" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('exp_name') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Expense Category</label>
                        <select wire:model="exp_category_id" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3">
                            <option value="">-- Select Category --</option>
                            @foreach ($expenseCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('exp_category_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Custom Split Account Name (Optional)</label>
                        <input type="text" wire:model="exp_split" placeholder="e.g. Office Rent, Equipment" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('exp_split') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Cheque Number (Num)</label>
                        <input type="text" wire:model="exp_num" placeholder="e.g. CHQ-100234" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('exp_num') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Description / Memo</label>
                        <textarea wire:model="exp_description" rows="2" placeholder="Cheque expense description..." class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3"></textarea>
                        @error('exp_description') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-border pt-4">
                    <button type="button" wire:click="$set('showExpenseModal', false)" class="px-4 py-2 text-sm rounded-lg border border-border bg-background hover:bg-accent">Cancel</button>
                    <button type="button" wire:click="saveExpense" class="px-4 py-2 text-sm rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700">Record Cheque Expense</button>
                </div>
            </div>
        </div>
    @endif

    <!-- 3. Cheque Drawing Modal -->
    @if ($showDrawingModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
            <div class="bg-card text-card-foreground rounded-xl max-w-md w-full border border-border shadow-xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-foreground">Record Owner Cheque Drawing</h3>
                    <button type="button" wire:click="$set('showDrawingModal', false)" class="text-muted-foreground hover:text-foreground">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Transaction Date *</label>
                        <input type="date" wire:model="draw_date" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('draw_date') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Amount *</label>
                        <input type="number" step="0.01" min="0.01" wire:model="draw_amount" placeholder="0.00" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('draw_amount') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Owner / Partner Name</label>
                        <input type="text" wire:model="draw_name" placeholder="e.g. Managing Director / Partner" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('draw_name') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Cheque Number (Num)</label>
                        <input type="text" wire:model="draw_num" placeholder="e.g. CHQ-550011" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('draw_num') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Item Split Account</label>
                        <input type="text" wire:model="draw_split" placeholder="Owner Drawings" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('draw_split') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Description / Memo</label>
                        <textarea wire:model="draw_description" rows="2" placeholder="Owner withdrawal via cheque..." class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3"></textarea>
                        @error('draw_description') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-border pt-4">
                    <button type="button" wire:click="$set('showDrawingModal', false)" class="px-4 py-2 text-sm rounded-lg border border-border bg-background hover:bg-accent">Cancel</button>
                    <button type="button" wire:click="saveDrawing" class="px-4 py-2 text-sm rounded-lg bg-amber-600 text-white font-semibold hover:bg-amber-700">Record Cheque Drawing</button>
                </div>
            </div>
        </div>
    @endif

    <!-- 4. Beginning Balance Configuration Modal -->
    @if ($showBeginningBalanceModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
            <div class="bg-card text-card-foreground rounded-xl max-w-md w-full border border-border shadow-xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-foreground">Configure Bank Beginning Balance</h3>
                    <button type="button" wire:click="$set('showBeginningBalanceModal', false)" class="text-muted-foreground hover:text-foreground">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Beginning Balance Amount *</label>
                        <input type="number" step="0.01" min="0" wire:model="setting_balance" placeholder="0.00" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('setting_balance') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-muted-foreground mt-1">Starting bank statement baseline fund as recorded in your books.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Baseline Starting Date</label>
                        <input type="date" wire:model="setting_date" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('setting_date') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-muted-foreground mt-1">The date on which this opening bank balance was established.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-border pt-4">
                    <button type="button" wire:click="$set('showBeginningBalanceModal', false)" class="px-4 py-2 text-sm rounded-lg border border-border bg-background hover:bg-accent">Cancel</button>
                    <button type="button" wire:click="saveBeginningBalance" class="px-4 py-2 text-sm rounded-lg bg-primary text-primary-foreground font-semibold hover:bg-primary/90">Save Configuration</button>
                </div>
            </div>
        </div>
    @endif

    <!-- 5. Edit Transaction Modal -->
    @if ($showEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
            <div class="bg-card text-card-foreground rounded-xl max-w-md w-full border border-border shadow-xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-foreground">
                        Edit {{ match($edit_transaction_type) {
                            'bank_investment' => 'Bank Investment',
                            'expense' => 'Cheque Expense',
                            'drawing' => 'Owner Cheque Drawing',
                            default => 'Transaction',
                        } }}
                    </h3>
                    <button type="button" wire:click="closeEditModal" class="text-muted-foreground hover:text-foreground">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Transaction Date *</label>
                        <input type="date" wire:model="edit_date" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('edit_date') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Amount *</label>
                        <input type="number" step="0.01" min="0.01" wire:model="edit_amount" placeholder="0.00" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('edit_amount') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">
                            {{ match($edit_transaction_type) {
                                'bank_investment' => 'Investor / Source Name',
                                'expense' => 'Payee Name',
                                'drawing' => 'Owner / Partner Name',
                                default => 'Name / Contact',
                            } }}
                        </label>
                        <input type="text" wire:model="edit_name" placeholder="Name..." class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('edit_name') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    @if ($edit_transaction_type === 'expense')
                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Expense Category</label>
                            <select wire:model="edit_finance_category_id" class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3">
                                <option value="">-- Select Category --</option>
                                @foreach ($expenseCategories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            @error('edit_finance_category_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Split Account Name</label>
                        <input type="text" wire:model="edit_split_account" placeholder="Split account name..." class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('edit_split_account') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">
                            {{ match($edit_transaction_type) {
                                'expense', 'drawing' => 'Cheque Number (Num)',
                                default => 'Reference Number (Num)',
                            } }}
                        </label>
                        <input type="text" wire:model="edit_reference_number" placeholder="Reference / Cheque number..." class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3" />
                        @error('edit_reference_number') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1">Description / Memo</label>
                        <textarea wire:model="edit_description" rows="2" placeholder="Description..." class="w-full text-sm rounded-lg border-border bg-background text-foreground py-2 px-3"></textarea>
                        @error('edit_description') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-border pt-4">
                    <button type="button" wire:click="closeEditModal" class="px-4 py-2 text-sm rounded-lg border border-border bg-background hover:bg-accent">Cancel</button>
                    <button type="button" wire:click="updateTransaction" class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700">Update Transaction</button>
                </div>
            </div>
        </div>
    @endif
</div>
