<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductDetail extends Component
{
    public ?Product $product = null;

    public function render()
    {
        return view('livewire.products.product-detail');
    }

    #[On('show-product')]
    public function show(Product $product): void
    {
        $this->product = $product->load(['category', 'unit', 'company', 'priceHistories.changedBy']);
        $this->dispatch('open-modal', name: 'product-detail-modal');
    }

    public function closeModal(): void
    {
        $this->product = null;
    }
}
