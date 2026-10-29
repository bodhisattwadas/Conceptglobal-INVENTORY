<?php

namespace App\Livewire\Services;

use App\Models\Service;
use Livewire\Attributes\On;
use Livewire\Component;

class ServiceDetail extends Component
{
    public ?Service $service = null;

    public function render()
    {
        return view('livewire.services.service-detail');
    }

    #[On('show-service')]
    public function show(Service $service): void
    {
        $this->service = $service;
        $this->dispatch('open-modal', name: 'service-detail-modal');
    }

    public function closeModal(): void
    {
        $this->service = null;
    }
}
