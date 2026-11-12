<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryReconciliation;
use App\Services\InventoryReconciliationService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryReconciliationTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'pending'; // 'pending', 'reconciled', 'all'

    public string $dateFilter = 'all'; // 'all', 'today', 'this_week', 'this_month'

    public int $perPage = 15;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public array $selectedIds = [];

    public bool $selectAll = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'pending'],
        'dateFilter' => ['except' => 'all'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatingDateFilter(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selectedIds = InventoryReconciliation::query()
                ->when($this->statusFilter !== 'all', fn (Builder $q) => $q->where('status', $this->statusFilter))
                ->when($this->statusFilter === 'all', fn (Builder $q) => $q->where('status', 'pending'))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        } else {
            $this->selectedIds = [];
        }
    }

    public function createPurchaseOrderForSelected(): mixed
    {
        if (empty($this->selectedIds)) {
            $this->dispatch('toast', [
                'message' => 'Please select at least one pending reconciliation item.',
                'type' => 'warning',
            ]);

            return null;
        }

        $items = InventoryReconciliation::with('matchedProduct')
            ->whereIn('id', $this->selectedIds)
            ->get();

        $alreadyReconciled = $items->filter(fn ($i) => ! $i->isPending());
        if ($alreadyReconciled->isNotEmpty()) {
            $this->dispatch('toast', [
                'message' => 'Some selected items are already reconciled. Please only select pending items.',
                'type' => 'warning',
            ]);

            return null;
        }

        $unmatched = $items->filter(fn ($i) => empty($i->matched_product_id));
        if ($unmatched->isNotEmpty()) {
            $first = $unmatched->first();
            $this->dispatch('toast', [
                'message' => "'{$first->item_name}' is not in the Product Master yet. Please create it in Master before ordering.",
                'type' => 'warning',
            ]);

            $this->openReconcileModal($first->id, 'create');

            return null;
        }

        return redirect()->route('purchases.create', [
            'recon_ids' => implode(',', $this->selectedIds),
        ]);
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openReconcileModal(int $id, string $tab = 'po'): void
    {
        $this->dispatch('open-reconciliation-modal', id: $id, tab: $tab);
    }

    public function openEditModal(int $id): void
    {
        $this->dispatch('open-edit-reconciliation-modal', id: $id);
    }

    public function autoReconcileSingle(int $id, InventoryReconciliationService $service): void
    {
        $recon = InventoryReconciliation::findOrFail($id);
        $reconciled = $service->autoReconcile($recon, Auth::id());

        if ($reconciled) {
            $this->dispatch('toast', [
                'message' => "Successfully auto-reconciled '{$recon->item_name}' with master product!",
                'type' => 'success',
            ]);
        } else {
            $this->dispatch('toast', [
                'message' => "Could not auto-reconcile '{$recon->item_name}'. No exact barcode or name match found in master.",
                'type' => 'warning',
            ]);
        }
    }

    public function autoReconcileAllMatched(InventoryReconciliationService $service): void
    {
        $pendingItems = InventoryReconciliation::where('status', 'pending')->get();
        $count = 0;

        foreach ($pendingItems as $recon) {
            if ($service->autoReconcile($recon, Auth::id())) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->dispatch('toast', [
                'message' => "Auto-reconciled {$count} item(s) successfully against product master.",
                'type' => 'success',
            ]);
        } else {
            $this->dispatch('toast', [
                'message' => 'No pending items could be automatically matched. Please reconcile manually.',
                'type' => 'info',
            ]);
        }
    }

    #[On('reconciliation-updated')]
    public function refreshTable(): void
    {
        // Re-renders the component
    }

    public function render(): View
    {
        $counts = [
            'pending' => InventoryReconciliation::where('status', 'pending')->count(),
            'reconciled' => InventoryReconciliation::where('status', 'reconciled')->count(),
            'all' => InventoryReconciliation::count(),
        ];

        $query = InventoryReconciliation::query()
            ->with(['sale', 'saleItem', 'matchedProduct', 'reconciledProduct', 'targetPurchase', 'reconciledPurchase', 'creator', 'reconciler'])
            ->when($this->statusFilter !== 'all', function (Builder $q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->search !== '', function (Builder $q) {
                $search = '%'.trim($this->search).'%';
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('reconciliation_number', 'like', $search)
                        ->orWhere('item_name', 'like', $search)
                        ->orWhere('barcode', 'like', $search)
                        ->orWhereHas('sale', fn ($sq) => $sq->where('invoice_number', 'like', $search))
                        ->orWhereHas('matchedProduct', fn ($pq) => $pq->where('name', 'like', $search)->orWhere('sku', 'like', $search));
                });
            })
            ->when($this->dateFilter !== 'all', function (Builder $q) {
                match ($this->dateFilter) {
                    'today' => $q->whereDate('created_at', Carbon::today()),
                    'this_week' => $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
                    'this_month' => $q->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year),
                    default => null,
                };
            })
            ->orderBy($this->sortField, $this->sortDirection);

        $reconciliations = $query->paginate($this->perPage);

        return view('livewire.inventory.inventory-reconciliation-table', [
            'reconciliations' => $reconciliations,
            'counts' => $counts,
        ]);
    }
}
