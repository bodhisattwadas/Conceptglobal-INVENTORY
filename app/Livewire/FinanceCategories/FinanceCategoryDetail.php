<?php

namespace App\Livewire\FinanceCategories;

use App\Models\FinanceCategory;
use Livewire\Attributes\On;
use Livewire\Component;

class FinanceCategoryDetail extends Component
{
    public ?FinanceCategory $category = null;

    #[On('view-finance-category')]
    public function show(FinanceCategory $category): void
    {
        $this->category = $category;
        $this->dispatch('open-modal', name: 'finance-category-detail-modal');
    }

    public function closeModal()
    {
        $this->category = null;
    }

    public function render()
    {
        return view('livewire.finance-categories.finance-category-detail');
    }
}
