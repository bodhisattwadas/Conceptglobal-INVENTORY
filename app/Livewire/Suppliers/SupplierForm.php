<?php

namespace App\Livewire\Suppliers;

use Exception;
use Livewire\Component;
use App\Models\Company;
use App\Models\Supplier;
use App\DTOs\SupplierData;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use App\Services\SupplierService;
use Illuminate\Support\Facades\Storage;

class SupplierForm extends Component
{
    use WithFileUploads;

    public ?Supplier $supplier = null;

    public string $name = '';
    public string $legal_name = '';
    public string $trade_name = '';
    public string $supplier_type = '';
    public string $registration_number = '';
    public string $tax_id = '';
    public string $website = '';
    public string $industry = '';

    public string $contact_person = '';

    public string $email = '';
    public string $accounts_email = '';
    public string $purchase_email = '';

    public string $phone = '';
    public string $alternate_phone = '';

    public string $address = '';
    public string $address_line_1 = '';
    public string $address_line_2 = '';
    public string $city = '';
    public string $state = '';
    public string $postal_code = '';
    public string $country = '';
    public string $bank_name = '';
    public string $bank_branch = '';
    public string $account_name = '';
    public string $account_number = '';
    public string $account_type = '';
    public string $ifsc_code = '';
    public string $swift_bic = '';
    public string $beneficiary_name = '';
    public string $bank_country = '';
    public string $status = 'active';
    public array $company_ids = [];
    public $blank_cheque = null;
    public $gst_document = null;
    public ?string $current_blank_cheque_path = null;
    public ?string $current_gst_document_path = null;

    public string $notes = '';

    public bool $isEditing = false;
    public bool $asPage = false;

    public function mount(bool $asPage = false, ?Supplier $supplier = null): void
    {
        $this->asPage = $asPage;

        if ($supplier?->exists) {
            $this->fillFromSupplier($supplier);
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'supplier_type' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:suppliers,email,' . ($this->supplier?->id)],
            'accounts_email' => ['nullable', 'email', 'max:255'],
            'purchase_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'required_with:account_number', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_type' => ['nullable', 'in:Savings,Current'],
            'ifsc_code' => ['nullable', 'string', 'max:50'],
            'swift_bic' => ['nullable', 'string', 'max:50'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            'bank_country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
            'company_ids' => ['array'],
            'company_ids.*' => ['exists:companies,id'],
            'blank_cheque' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'gst_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function render()
    {
        return view('livewire.suppliers.supplier-form', [
            'companies' => Company::active()->orderBy('company_name')->get(),
        ]);
    }

    public function updatedBlankCheque(): void
    {
        $this->validateOnly('blank_cheque');
    }

    public function updatedGstDocument(): void
    {
        $this->validateOnly('gst_document');
    }

    #[On('create-supplier')]
    public function create(): void
    {
        $this->reset();
        $this->isEditing = false;
        $this->dispatch('open-modal', name: 'supplier-modal');
    }

    #[On('edit-supplier')]
    public function edit(Supplier $supplier): void
    {
        $this->resetValidation();
        $this->fillFromSupplier($supplier);
        $this->dispatch('open-modal', name: 'supplier-modal');
    }

    private function fillFromSupplier(Supplier $supplier): void
    {
        $this->supplier = $supplier;
        $supplier->load('companies');

        foreach (array_keys($this->rules()) as $field) {
            if (in_array($field, ['company_ids', 'blank_cheque', 'gst_document'], true) || str_contains($field, '.')) {
                continue;
            }

            $this->{$field} = (string) ($supplier->{$field} ?? '');
        }

        $this->account_number = '';
        $this->blank_cheque = null;
        $this->gst_document = null;
        $this->current_blank_cheque_path = $supplier->blank_cheque_path;
        $this->current_gst_document_path = $supplier->gst_document_path;
        $this->company_ids = $supplier->companies->pluck('id')->all();

        $this->isEditing = true;
    }

    public function save(SupplierService $service): void
    {
        $validated = $this->validate($this->rules());
        $oldBlankChequePath = $this->current_blank_cheque_path;
        $oldGstDocumentPath = $this->current_gst_document_path;

        $blankChequePath = $this->storeDocument($this->blank_cheque, 'supplier-documents/blank-cheques', $oldBlankChequePath);
        $gstDocumentPath = $this->storeDocument($this->gst_document, 'supplier-documents/gst', $oldGstDocumentPath);
        unset($validated['blank_cheque'], $validated['gst_document']);
        $validated['blank_cheque_path'] = $blankChequePath;
        $validated['gst_document_path'] = $gstDocumentPath;

        try {
            $supplierData = SupplierData::fromArray($validated);

            if ($this->isEditing && $this->supplier?->exists) {
                $supplier = $service->updateSupplier($this->supplier, $supplierData);
                $message = 'Supplier updated successfully.';
            } else {
                $supplier = $service->createSupplier($supplierData);
                $message = 'Supplier created successfully.';
            }

            $this->supplier = $supplier->refresh();
            $this->current_blank_cheque_path = $this->supplier->blank_cheque_path;
            $this->current_gst_document_path = $this->supplier->gst_document_path;
            $this->blank_cheque = null;
            $this->gst_document = null;
            $this->deleteReplacedDocument($oldBlankChequePath, $blankChequePath);
            $this->deleteReplacedDocument($oldGstDocumentPath, $gstDocumentPath);

            $this->dispatch('close-modal', name: 'supplier-modal');
            $this->dispatch('pg:eventRefresh-default');
            $this->dispatch('pg:eventRefresh-supplier-table');
            $this->dispatch('toast', message: $message, type: 'success');

            if ($this->asPage) {
                $this->redirectRoute('suppliers.index', navigate: true);

                return;
            }

            $this->reset();

        } catch (Exception $e) {
            if ($blankChequePath !== $this->current_blank_cheque_path) {
                Storage::disk('public')->delete($blankChequePath);
            }
            if ($gstDocumentPath !== $this->current_gst_document_path) {
                Storage::disk('public')->delete($gstDocumentPath);
            }
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    private function deleteReplacedDocument(?string $oldPath, ?string $newPath): void
    {
        if ($oldPath && $newPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function storeDocument(mixed $file, string $directory, ?string $currentPath): ?string
    {
        if (! $file) {
            return $currentPath;
        }

        return $file->store($directory, 'public');
    }
}
