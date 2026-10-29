<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Unit;
use App\Services\InventoryAdjustmentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class StockAdjustmentModal extends Component
{
    public bool $isOpen = false;

    // Adjustment type: 'reallocation' or 'quantity_decrease' or 'quantity_increase'
    public string $adjustmentType = 'reallocation';

    // Source Product & Batch
    public ?int $source_product_id = null;

    public ?int $source_purchase_item_id = null;

    public int $quantity = 1;

    public string $reason = 'misallocation_split';

    public string $notes = '';

    // Target Product (for reallocation)
    public ?int $target_product_id = null;

    public ?float $target_mrp = null;

    // Quick Add Product state
    public bool $showQuickProduct = false;

    public string $quick_name = '';

    public string $quick_sku = '';

    public string $quick_product_code = '';

    public ?int $quick_company_id = null;

    public ?int $quick_category_id = null;

    public ?int $quick_unit_id = null;

    public ?float $quick_mrp = null;

    #[On('open-adjustment-modal')]
    public function openModal(?int $productId = null, ?int $batchId = null): void
    {
        $this->resetValidation();
        $this->isOpen = true;
        $this->adjustmentType = 'reallocation';
        $this->showQuickProduct = false;
        $this->notes = '';
        $this->reason = 'misallocation_split';
        $this->target_product_id = null;
        $this->target_mrp = null;

        if ($productId) {
            $this->source_product_id = $productId;
            $this->loadSourceBatches();

            if ($batchId) {
                $this->source_purchase_item_id = $batchId;
            }
        } else {
            $this->source_product_id = null;
            $this->source_purchase_item_id = null;
        }

        $this->quantity = 1;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetQuickProduct();
    }

    public function updatedSourceProductId(): void
    {
        $this->source_purchase_item_id = null;
        $this->loadSourceBatches();
    }

    public function updatedSourcePurchaseItemId(): void
    {
        $batch = $this->getSelectedBatch();
        if ($batch && $this->quantity > (int) $batch->received_quantity) {
            $this->quantity = max(1, (int) $batch->received_quantity);
        }
    }

    public function updatedTargetProductId(): void
    {
        if ($this->target_product_id) {
            $target = Product::find($this->target_product_id);
            if ($target) {
                $this->target_mrp = (float) ($target->mrp ?: $target->selling_price ?: 0);
            }
        }
    }

    public function toggleQuickProduct(): void
    {
        $this->showQuickProduct = ! $this->showQuickProduct;
        if ($this->showQuickProduct) {
            $this->resetQuickProduct();
            // Prepopulate company/category/unit from source product if available
            if ($this->source_product_id) {
                $source = Product::find($this->source_product_id);
                if ($source) {
                    $this->quick_company_id = $source->company_id;
                    $this->quick_category_id = $source->category_id;
                    $this->quick_unit_id = $source->unit_id;
                    $this->quick_mrp = (float) $source->mrp;
                }
            }
        }
    }

    public function resetQuickProduct(): void
    {
        $this->quick_name = '';
        $this->quick_sku = '';
        $this->quick_product_code = '';
        $this->quick_company_id = null;
        $this->quick_category_id = null;
        $this->quick_unit_id = null;
        $this->quick_mrp = null;
    }

    public function saveQuickProduct(InventoryAdjustmentService $service): void
    {
        $this->validate([
            'quick_name' => ['required', 'string', 'max:255'],
            'quick_sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'quick_product_code' => ['nullable', 'string', 'max:100'],
            'quick_company_id' => ['nullable', 'exists:companies,id'],
            'quick_category_id' => ['nullable', 'exists:categories,id'],
            'quick_unit_id' => ['nullable', 'exists:units,id'],
            'quick_mrp' => ['nullable', 'numeric', 'min:0'],
        ]);

        $batch = $this->getSelectedBatch();
        $purchasePrice = $batch ? (float) $batch->unit_price : 0;

        $newProduct = $service->createQuickProduct([
            'name' => $this->quick_name,
            'sku' => $this->quick_sku,
            'product_code' => $this->quick_product_code,
            'company_id' => $this->quick_company_id,
            'category_id' => $this->quick_category_id,
            'unit_id' => $this->quick_unit_id,
            'mrp' => $this->quick_mrp ?? 0,
            'purchase_price' => $purchasePrice,
        ], Auth::id() ?? 1);

        $this->target_product_id = $newProduct->id;
        $this->target_mrp = (float) $newProduct->mrp;
        $this->showQuickProduct = false;
        $this->resetQuickProduct();

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => "Created product '{$newProduct->name}' successfully.",
        ]);
    }

    public function submitAdjustment(InventoryAdjustmentService $service): void
    {
        if ($this->adjustmentType === 'reallocation') {
            $this->validate([
                'source_product_id' => ['required', 'exists:products,id'],
                'source_purchase_item_id' => ['required', 'exists:purchase_items,id'],
                'target_product_id' => ['required', 'exists:products,id', 'different:source_product_id'],
                'quantity' => ['required', 'integer', 'min:1'],
                'target_mrp' => ['nullable', 'numeric', 'min:0'],
                'reason' => ['required', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $batch = $this->getSelectedBatch();
            if (! $batch || (int) $batch->received_quantity < $this->quantity) {
                $maxAvailable = $batch ? (int) $batch->received_quantity : 0;
                $this->addError('quantity', "The requested quantity exceeds available batch quantity ({$maxAvailable}).");

                return;
            }

            try {
                $adjustment = $service->reallocateStock([
                    'source_product_id' => $this->source_product_id,
                    'source_purchase_item_id' => $this->source_purchase_item_id,
                    'target_product_id' => $this->target_product_id,
                    'quantity' => $this->quantity,
                    'target_mrp' => $this->target_mrp,
                    'reason' => $this->reason,
                    'notes' => $this->notes,
                ], Auth::id() ?? 1);

                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => "Stock reallocated successfully (#{$adjustment->adjustment_number}).",
                ]);

                $this->isOpen = false;
                $this->dispatch('pg:eventRefresh-inventory-table');
                $this->dispatch('inventory-adjusted');
            } catch (\Throwable $e) {
                $this->addError('quantity', $e->getMessage());
            }
        } else {
            // General quantity adjustment (decrease / increase)
            $this->validate([
                'source_product_id' => ['required', 'exists:products,id'],
                'source_purchase_item_id' => ['nullable', 'exists:purchase_items,id'],
                'quantity' => ['required', 'integer', 'min:1'],
                'reason' => ['required', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            try {
                $adjustment = $service->adjustQuantity([
                    'product_id' => $this->source_product_id,
                    'purchase_item_id' => $this->source_purchase_item_id,
                    'type' => $this->adjustmentType,
                    'quantity' => $this->quantity,
                    'reason' => $this->reason,
                    'notes' => $this->notes,
                ], Auth::id() ?? 1);

                $this->dispatch('toast', [
                    'type' => 'success',
                    'message' => "Stock adjustment completed successfully (#{$adjustment->adjustment_number}).",
                ]);

                $this->isOpen = false;
                $this->dispatch('pg:eventRefresh-inventory-table');
                $this->dispatch('inventory-adjusted');
            } catch (\Throwable $e) {
                $this->addError('quantity', $e->getMessage());
            }
        }
    }

    public function getSelectedBatch(): ?PurchaseItem
    {
        if (! $this->source_purchase_item_id) {
            return null;
        }

        return PurchaseItem::with('purchase.supplier')->find($this->source_purchase_item_id);
    }

    private function loadSourceBatches(): void
    {
        if (! $this->source_product_id) {
            return;
        }

        $batches = PurchaseItem::query()
            ->where('product_id', $this->source_product_id)
            ->where('received_quantity', '>', 0)
            ->with('purchase.supplier')
            ->orderBy('id', 'desc')
            ->get();

        if ($batches->count() === 1 && ! $this->source_purchase_item_id) {
            $this->source_purchase_item_id = $batches->first()->id;
        }
    }

    public function render()
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'product_code', 'quantity', 'mrp']);

        $sourceBatches = collect();
        if ($this->source_product_id) {
            $sourceBatches = PurchaseItem::query()
                ->where('product_id', $this->source_product_id)
                ->where('received_quantity', '>', 0)
                ->with('purchase.supplier')
                ->orderBy('id', 'desc')
                ->get();
        }

        $companies = Company::active()->orderBy('company_name')->get();
        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();

        return view('livewire.inventory.stock-adjustment-modal', [
            'products' => $products,
            'sourceBatches' => $sourceBatches,
            'selectedBatch' => $this->getSelectedBatch(),
            'companies' => $companies,
            'categories' => $categories,
            'units' => $units,
        ]);
    }
}
