<?php

namespace App\Livewire\Companies;

use App\Models\Company;
use App\Services\CompanyService;
use Livewire\Attributes\On;
use Livewire\Component;

class CompanyForm extends Component
{
    public ?Company $company = null;

    public bool $isEditing = false;

    public string $company_code = '';

    public string $company_name = '';

    public string $short_name = '';

    public string $phone = '';

    public string $status = 'active';

    protected function rules(): array
    {
        return [
            'company_code' => ['required', 'string', 'max:50', 'unique:companies,company_code,'.($this->company?->id)],
            'company_name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\s*(?:\+91|91|0)?[\s-]*[6-9]\d{4}[\s-]*\d{5}\s*$/'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function render()
    {
        return view('livewire.companies.company-form');
    }

    #[On('create-company')]
    public function create(): void
    {
        $this->reset();
        $this->status = 'active';
        $this->dispatch('open-modal', name: 'company-modal');
    }

    #[On('edit-company')]
    public function edit(Company $company): void
    {
        $this->company = $company;
        $this->isEditing = true;
        foreach (array_keys($this->rules()) as $field) {
            $this->{$field} = (string) ($company->{$field} ?? '');
        }
        $this->dispatch('open-modal', name: 'company-modal');
    }

    public function save(CompanyService $service): void
    {
        $validated = $this->validate();
        $validated['company_type'] = 'Brand';
        $validated['phone'] = normalize_indian_phone($validated['phone'] ?? null);
        $validated['status'] = 'active';

        $this->isEditing && $this->company
            ? $service->update($this->company, $validated)
            : $service->create($validated);

        $this->dispatch('close-modal', name: 'company-modal');
        $this->dispatch('pg:eventRefresh-company-table');
        $this->dispatch('toast', message: 'Company saved successfully.', type: 'success');
        $this->reset();
    }
}
