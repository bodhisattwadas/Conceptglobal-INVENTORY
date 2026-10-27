<?php

namespace App\Livewire\Products;

use App\DTOs\ProductData;
use App\Exceptions\ProductException;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductForm extends Component
{
    use WithFileUploads;

    public bool $isEditing = false;

    public bool $isCloning = false;

    public int $formKey = 0;

    public ?Product $product = null;

    // Form Fields
    public ?string $sku = null;

    public ?string $product_code = null;

    public ?string $duplicateBarcodeWarning = null;

    public string $name = '';

    public ?int $category_id = null;

    public ?int $unit_id = null;

    public ?int $company_id = null;

    public int $mrp = 0;

    public int $purchase_price = 0;

    public int $selling_price = 0;

    public int $min_stock = 0;

    public bool $is_active = true;

    public string $description = '';

    public string $notes = '';

    public $image = null;

    public ?string $currentImagePath = null;

    // Select Options (Removed for AJAX)
    public ?string $categoryName = null;

    public ?string $unitName = null;

    public ?string $brandName = null;

    public function mount()
    {
        // No options to load
    }

    public function render()
    {
        return view('livewire.products.product-form');
    }

    #[On('create-product')]
    public function create(): void
    {
        $this->reset(['sku', 'product_code', 'duplicateBarcodeWarning', 'name', 'category_id', 'unit_id', 'company_id', 'brandName', 'mrp', 'purchase_price', 'selling_price', 'min_stock', 'description', 'notes', 'image', 'currentImagePath', 'product', 'isEditing', 'isCloning', 'categoryName', 'unitName']);
        $this->is_active = true;
        $this->formKey++;
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'product-form-modal');
    }

    #[On('edit-product')]
    public function edit(Product $product): void
    {
        $this->product = $product->load('priceHistories.changedBy');
        $this->sku = $product->sku;
        $this->product_code = $product->product_code;
        $this->duplicateBarcodeWarning = null;
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->unit_id = $product->unit_id;
        $this->company_id = $product->company_id;
        $this->mrp = (int) $product->mrp;
        $this->purchase_price = (int) $product->mrp;
        $this->selling_price = (int) $product->mrp;
        $this->min_stock = $product->min_stock;
        $this->is_active = $product->is_active;
        $this->description = $product->description ?? '';
        $this->notes = $product->notes ?? '';
        $this->image = null;
        $this->currentImagePath = $product->image_path;

        // Set initial labels for TomSelect
        $this->categoryName = $product->category ? $product->category->name : null;
        $this->unitName = $product->unit ? "{$product->unit->name} ({$product->unit->symbol})" : null;
        $this->brandName = $product->company ? ($product->company->short_name ?: $product->company->company_name) : null;

        $this->isEditing = true;
        $this->isCloning = false;
        $this->formKey++;
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'product-form-modal');
    }

    #[On('clone-product')]
    public function cloneProduct(Product $product): void
    {
        $this->product = null;
        $this->sku = null;
        $this->product_code = null;
        $this->duplicateBarcodeWarning = null;
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->unit_id = $product->unit_id;
        $this->company_id = $product->company_id;
        $this->mrp = (int) $product->mrp;
        $this->purchase_price = (int) $product->mrp;
        $this->selling_price = (int) $product->mrp;
        $this->min_stock = $product->min_stock;
        $this->is_active = $product->is_active;
        $this->description = $product->description ?? '';
        $this->notes = $product->notes ?? '';
        $this->image = null;
        $this->currentImagePath = null;

        // Set initial labels for TomSelect
        $this->categoryName = $product->category ? $product->category->name : null;
        $this->unitName = $product->unit ? "{$product->unit->name} ({$product->unit->symbol})" : null;
        $this->brandName = $product->company ? ($product->company->short_name ?: $product->company->company_name) : null;

        $this->isEditing = false;
        $this->isCloning = true;
        $this->formKey++;
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'product-form-modal');
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'product_code' => ['nullable', 'string', 'max:100'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($this->product?->id),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'company_id' => ['required', 'exists:companies,id'],
            'mrp' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function updatedImage(): void
    {
        $this->validateOnly('image');
    }

    public function updatedProductCode(?string $value): void
    {
        $this->checkDuplicateBarcode($value);
    }

    public function checkDuplicateBarcode(?string $value): void
    {
        $code = trim((string) $value);

        if ($code === '') {
            $this->duplicateBarcodeWarning = null;

            return;
        }

        $existing = Product::query()
            ->where('product_code', $code)
            ->when($this->product?->id, fn ($q) => $q->where('id', '!=', $this->product->id))
            ->first();

        if ($existing) {
            $skuInfo = $existing->sku ? " (SKU: {$existing->sku})" : '';
            $this->duplicateBarcodeWarning = "Warning: Barcode '{$code}' is already assigned to '{$existing->name}'{$skuInfo}.";
        } else {
            $this->duplicateBarcodeWarning = null;
        }
    }

    public function save(ProductService $service): void
    {
        $validated = $this->validate();
        $validated['purchase_price'] = $validated['mrp'];
        $validated['selling_price'] = $validated['mrp'];
        $validated['image_path'] = $this->image
            ? $this->image->store('products', 'public')
            : null;

        $data = ProductData::fromArray($validated);

        try {
            if ($this->isEditing && $this->product) {
                $this->product = $service->updateProduct($this->product, $data);
                $this->currentImagePath = $this->product->image_path;
                $message = 'Product updated successfully.';
            } else {
                $this->product = $service->createProduct($data);
                $this->currentImagePath = $this->product->image_path;
                $message = 'Product created successfully.';
            }

            $this->dispatch('close-modal', name: 'product-form-modal');
            $this->dispatch('pg:eventRefresh-product-table');
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (ProductException $e) {
            $this->deleteUploadedImage($validated['image_path']);
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        } catch (\Throwable $e) {
            $this->deleteUploadedImage($validated['image_path']);
            $this->dispatch('toast', message: 'An unexpected error occurred.', type: 'error');
        }
    }

    private function deleteUploadedImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
