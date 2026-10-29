<?php

namespace App\Livewire\Services;

use App\DTOs\ServiceData;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class ServiceForm extends Component
{
    public bool $isEditing = false;

    public ?Service $serviceModel = null;

    public string $name = '';

    public string $code = '';

    public string $cost = '';

    public string $description = '';

    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('services', 'code')->ignore($this->serviceModel?->id),
            ],
            'cost' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function render()
    {
        return view('livewire.services.service-form');
    }

    #[On('create-service')]
    public function create(): void
    {
        $this->reset([
            'name', 'code', 'cost', 'description', 'serviceModel', 'isEditing',
        ]);
        $this->is_active = true;
        $this->dispatch('open-modal', name: 'service-form-modal');
    }

    #[On('edit-service')]
    public function edit(Service $service): void
    {
        $this->serviceModel = $service;
        $this->name = $service->name;
        $this->code = $service->code ?? '';
        $this->cost = (string) $service->cost;
        $this->description = $service->description ?? '';
        $this->is_active = (bool) $service->is_active;
        $this->isEditing = true;
        $this->dispatch('open-modal', name: 'service-form-modal');
    }

    public function save(ServiceService $service): void
    {
        $validated = $this->validate();
        $data = ServiceData::fromArray($validated);

        try {
            if ($this->isEditing && $this->serviceModel) {
                $service->updateService($this->serviceModel, $data);
                $message = 'Service updated successfully.';
            } else {
                $service->createService($data);
                $message = 'Service created successfully.';
            }

            $this->dispatch('close-modal', name: 'service-form-modal');
            $this->dispatch('pg:eventRefresh-service-table');
            $this->dispatch('toast', message: $message, type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage() ?: 'An unexpected error occurred.', type: 'error');
        }
    }
}
