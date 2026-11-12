<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\Company;
use App\Models\InventoryReconciliation;
use App\Models\Product;
use App\Models\Unit;
use App\Services\InventoryReconciliationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class InventoryReconciliationModal extends Component
{
    public bool $isOpen = false;

    public ?int $reconciliationId = null;

    public ?InventoryReconciliation $reconciliation = null;

    // Tabs: 'link', 'create', 'direct', 'edit'
    public string $activeTab = 'link';

    // --- Tab 1: Link Existing Product ---
    public string $productSearch = '';

    public ?int $selectedProductId = null;

    public ?Product $selectedProduct = null;

    public bool $deductStock = true;

    public string $linkNotes = '';

    // --- Tab 2: Create New Product ---
    public string $create_name = '';

    public string $create_barcode = '';

    public string $create_sku = '';

    public ?int $create_company_id = null;

    public ?int $create_category_id = null;

    public ?int $create_unit_id = null;

    public float $create_selling_price = 0;

    public float $create_mrp = 0;

    public float $create_purchase_price = 0;

    public int $create_initial_stock = 0;

    public bool $create_deduct_sold = true;

    public string $create_notes = '';

    // --- Tab 3: Direct Mark ---
    public string $directNotes = '';

    // --- Tab 4: Edit Details (ALL options) ---
    public string $edit_item_name = '';

    public string $edit_barcode = '';

    public int $edit_quantity = 1;

    public float $edit_unit_price = 0;

    public float $edit_discount = 0;

    public float $edit_cost_price = 0;

    public string $edit_status = 'pending';

    public string $edit_notes = '';

    public bool $edit_sync_sale = true;

    #[On('open-reconciliation-modal')]
    public function openModal(int $id): void
    {
        $this->loadRecord($id, 'link');
    }

    #[On('open-edit-reconciliation-modal')]
    public function openEditModal(int $id): void
    {
        $this->loadRecord($id, 'edit');
    }

    protected function loadRecord(int $id, string $defaultTab = 'link'): void
    {
        $this->reconciliationId = $id;
        $this->reconciliation = InventoryReconciliation::with([
            'sale',
            'saleItem',
            'matchedProduct',
            'reconciledProduct',
            'creator',
        ])->findOrFail($id);

        $this->activeTab = $defaultTab;
        $this->isOpen = true;

        // Reset & Populate Link State
        $this->productSearch = $this->reconciliation->barcode ?: $this->reconciliation->item_name;
        $this->selectedProductId = $this->reconciliation->matched_product_id ?: $this->reconciliation->reconciled_product_id;
        $this->selectedProduct = $this->selectedProductId ? Product::with('unit', 'company')->find($this->selectedProductId) : null;
        $this->deductStock = true;
        $this->linkNotes = '';

        // Reset & Populate Create State
        $this->create_name = $this->reconciliation->item_name;
        $this->create_barcode = $this->reconciliation->barcode ?: '';
        $this->create_sku = Product::generateSku();
        $this->create_company_id = null;
        $this->create_category_id = null;
        $this->create_unit_id = null;
        $this->create_selling_price = (float) $this->reconciliation->unit_price;
        $this->create_mrp = (float) $this->reconciliation->unit_price;
        $this->create_purchase_price = (float) $this->reconciliation->cost_price;
        $this->create_initial_stock = (int) $this->reconciliation->quantity;
        $this->create_deduct_sold = true;
        $this->create_notes = '';

        // Reset & Populate Direct State
        $this->directNotes = '';

        // Reset & Populate Edit State
        $this->edit_item_name = $this->reconciliation->item_name;
        $this->edit_barcode = $this->reconciliation->barcode ?: '';
        $this->edit_quantity = (int) $this->reconciliation->quantity;
        $this->edit_unit_price = (float) $this->reconciliation->unit_price;
        $this->edit_discount = (float) $this->reconciliation->discount;
        $this->edit_cost_price = (float) $this->reconciliation->cost_price;
        $this->edit_status = $this->reconciliation->status;
        $this->edit_notes = $this->reconciliation->notes ?: '';
        $this->edit_sync_sale = true;
    }

    public function selectProduct(int $productId): void
    {
        $this->selectedProductId = $productId;
        $this->selectedProduct = Product::with('unit', 'company')->find($productId);
    }

    public function clearSelectedProduct(): void
    {
        $this->selectedProductId = null;
        $this->selectedProduct = null;
    }

    public function getSearchResultsProperty(): Collection
    {
        $term = trim($this->productSearch);
        if (strlen($term) < 2) {
            return collect();
        }

        return Product::query()
            ->with(['unit', 'company', 'inventoryStock'])
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('product_code', 'like', "%{$term}%");
            })
            ->limit(10)
            ->get();
    }

    public function submitLink(InventoryReconciliationService $service): void
    {
        $this->validate([
            'selectedProductId' => ['required', 'integer', 'exists:products,id'],
        ]);

        $service->linkExistingProduct(
            reconciliation: $this->reconciliation,
            productId: $this->selectedProductId,
            purchaseItemId: null,
            deductStock: $this->deductStock,
            notes: $this->linkNotes,
            userId: Auth::id()
        );

        $this->closeModal();
        $this->dispatch('toast', [
            'message' => 'Successfully linked product and reconciled item!',
            'type' => 'success',
        ]);
        $this->dispatch('reconciliation-updated');
    }

    public function submitCreate(InventoryReconciliationService $service): void
    {
        $this->validate([
            'create_name' => ['required', 'string', 'max:255'],
            'create_selling_price' => ['required', 'numeric', 'min:0'],
            'create_company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'create_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'create_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'create_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'create_initial_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $service->createNewProductAndReconcile(
            reconciliation: $this->reconciliation,
            productData: [
                'name' => $this->create_name,
                'sku' => $this->create_sku,
                'product_code' => $this->create_barcode ?: null,
                'company_id' => $this->create_company_id,
                'category_id' => $this->create_category_id,
                'unit_id' => $this->create_unit_id,
                'selling_price' => $this->create_selling_price,
                'mrp' => $this->create_mrp ?: $this->create_selling_price,
                'purchase_price' => $this->create_purchase_price,
            ],
            initialStock: $this->create_initial_stock,
            deductSoldStock: $this->create_deduct_sold,
            notes: $this->create_notes,
            userId: Auth::id()
        );

        $this->closeModal();
        $this->dispatch('toast', [
            'message' => "Created product '{$this->create_name}' and reconciled successfully!",
            'type' => 'success',
        ]);
        $this->dispatch('reconciliation-updated');
    }

    public function submitDirect(InventoryReconciliationService $service): void
    {
        $service->markAsReconciledDirectly(
            reconciliation: $this->reconciliation,
            notes: $this->directNotes,
            userId: Auth::id()
        );

        $this->closeModal();
        $this->dispatch('toast', [
            'message' => 'Marked entry as reconciled.',
            'type' => 'success',
        ]);
        $this->dispatch('reconciliation-updated');
    }

    public function submitEdit(InventoryReconciliationService $service): void
    {
        $this->validate([
            'edit_item_name' => ['required', 'string', 'max:255'],
            'edit_quantity' => ['required', 'integer', 'min:1'],
            'edit_unit_price' => ['required', 'numeric', 'min:0'],
            'edit_discount' => ['nullable', 'numeric', 'min:0'],
            'edit_cost_price' => ['nullable', 'numeric', 'min:0'],
            'edit_status' => ['required', 'string', 'in:pending,reconciled,rejected,cancelled'],
        ]);

        $service->updateReconciliationDetails(
            reconciliation: $this->reconciliation,
            data: [
                'item_name' => $this->edit_item_name,
                'barcode' => $this->edit_barcode,
                'quantity' => $this->edit_quantity,
                'unit_price' => $this->edit_unit_price,
                'discount' => $this->edit_discount,
                'cost_price' => $this->edit_cost_price,
                'status' => $this->edit_status,
                'notes' => $this->edit_notes,
            ],
            syncWithSaleItem: $this->edit_sync_sale
        );

        $this->closeModal();
        $this->dispatch('toast', [
            'message' => 'Reconciliation details updated successfully.',
            'type' => 'success',
        ]);
        $this->dispatch('reconciliation-updated');
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->reconciliation = null;
        $this->reconciliationId = null;
        $this->selectedProduct = null;
    }

    public function render(): View
    {
        $companies = Company::orderBy('company_name')->get();
        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();

        return view('livewire.inventory.inventory-reconciliation-modal', [
            'companies' => $companies,
            'categories' => $categories,
            'units' => $units,
            'searchResults' => $this->searchResults,
        ]);
    }
}
