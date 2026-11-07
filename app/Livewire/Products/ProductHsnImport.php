<?php

namespace App\Livewire\Products;

use App\Services\ProductHsnImportService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductHsnImport extends Component
{
    use WithFileUploads;

    public $file = null;

    public ?array $importSummary = null;

    public function render()
    {
        return view('livewire.products.product-hsn-import');
    }

    #[On('open-hsn-import')]
    public function openModal(): void
    {
        $this->file = null;
        $this->importSummary = null;
        $this->resetValidation();

        $this->dispatch('open-modal', name: 'product-hsn-import-modal');
    }

    public function downloadTemplate(ProductHsnImportService $service, bool $sample = false): BinaryFileResponse
    {
        return $service->exportTemplate($sample);
    }

    public function import(ProductHsnImportService $service): void
    {
        $this->validate([
            'file' => ['required', 'file', 'max:15360', 'mimes:xlsx,csv,txt'],
        ], [
            'file.required' => 'Please select an .xlsx or .csv spreadsheet to upload.',
            'file.mimes' => 'The file must be an Excel spreadsheet (.xlsx) or CSV (.csv).',
            'file.max' => 'The file may not be greater than 15MB.',
        ]);

        try {
            $extension = $this->file->getClientOriginalExtension();
            $path = $this->file->getRealPath();

            $summary = $service->importFromPath($path, $extension);
            $this->importSummary = $summary;
            $this->file = null;

            $this->dispatch('pg:eventRefresh-product-table');

            $message = "Sync complete: {$summary['updated_count']} products updated.";
            if ($summary['failed_count'] > 0) {
                $message .= " ({$summary['failed_count']} errors/unmatched)";
            }

            $this->dispatch('toast', message: $message, type: $summary['updated_count'] > 0 ? 'success' : 'info');
        } catch (\Throwable $e) {
            $this->addError('file', $e->getMessage());
        }
    }

    public function resetSummary(): void
    {
        $this->importSummary = null;
        $this->file = null;
        $this->resetValidation();
    }
}
