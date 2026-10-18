<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Livewire\Attributes\On;
use Livewire\Component;

class CategoryDetail extends Component
{
    public ?Category $category = null;

    public function render()
    {
        return view('livewire.categories.category-detail');
    }

    #[On('show-category')]
    public function show(Category $category)
    {
        $this->category = $category;
        $this->dispatch('open-modal', name: 'category-detail-modal');
    }

    public function closeModal()
    {
        $this->category = null;
    }
}
