<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use App\Services\SupplierService;
use App\Exceptions\SupplierException;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class SupplierTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'supplier-table';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable('supplier_export_' . now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),

            PowerGrid::header()
                ->showSearchInput(),

            PowerGrid::footer()
                ->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Supplier::query()->with('companies');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('contact_person')
            ->add('email')
            ->add('phone')
            ->add('phone_formatted', fn(Supplier $supplier) => format_indian_phone($supplier->phone))
            ->add('companies_list', function (Supplier $supplier) {
                $companies = $supplier->companies->pluck('company_name')->filter();
                $count = $companies->count();
                $title = $count > 0 ? $companies->join(', ') : 'No brands assigned';

                return sprintf(
                    '<span title="%s" class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg><span>%d</span></span>',
                    e($title),
                    $count
                );
            })
            ->add('companies_export', fn (Supplier $supplier) => $supplier->companies->pluck('company_name')->join(', '))
            ->add('status')
            ->add('status_badge', function (Supplier $supplier) {
                $status = strtolower($supplier->status ?: 'active');
                $isActive = $status === 'active';

                return sprintf(
                    '<span class="inline-flex min-w-20 items-center justify-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-inset %s">%s</span>',
                    $isActive
                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                        : 'bg-rose-50 text-rose-700 ring-rose-200',
                    e($isActive ? 'Active' : 'Inactive')
                );
            })
            ->add('address')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::action(''),

            Column::make('ID', 'id')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Contact Person', 'contact_person')
                ->sortable()
                ->searchable(),

            Column::make('Email', 'email')
                ->sortable()
                ->searchable(),

            Column::make('Phone', 'phone_formatted', 'phone')
                ->sortable()
                ->searchable(),

            Column::make('Brands', 'companies_list')
                ->headerAttribute('text-center')
                ->bodyAttribute('text-center')
                ->visibleInExport(false),

            Column::make('Brands / Companies', 'companies_export')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Status', 'status_badge', 'status')
                ->sortable()
                ->searchable(),

            // Exports
            Column::make('Address', 'address')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Notes', 'notes')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Bank Name', 'bank_name')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Bank Account Last 4', 'account_number_last4')
                ->hidden()
                ->visibleInExport(true),
        ];
    }

    public function actions(Supplier $row): array
    {
        return [
            Button::add('view')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>')
                ->class('bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md flex items-center justify-center')
                ->dispatch('show-supplier', ['supplier' => $row->id])
                ->tooltip('View Supplier'),

            Button::add('edit')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>')
                ->class('bg-amber-500 hover:bg-amber-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('suppliers.edit', ['supplier' => $row->id])
                ->tooltip('Edit Supplier'),

            Button::add('delete')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>')
                ->class('bg-red-500 hover:bg-red-600 text-white p-2 rounded-md flex items-center justify-center')
                ->dispatch('open-delete-modal', [
                    'component' => 'suppliers.supplier-table',
                    'method' => 'delete',
                    'params' => ['rowId' => $row->id],
                    'title' => 'Delete Supplier?',
                    'description' => "Are you sure you want to delete supplier '{$row->name}'? This action cannot be undone.",
                ])
                ->tooltip('Delete Supplier'),
        ];
    }

    #[\Livewire\Attributes\On('delete')]
    public function delete($rowId, SupplierService $supplierService): void
    {
        $supplier = Supplier::find($rowId);

        if ($supplier) {
            try {
                $supplierService->deleteSupplier($supplier);
                $this->dispatch('toast', message: 'Supplier deleted successfully.', type: 'success');
            } catch (\Exception $e) {
                $message = $e instanceof SupplierException
                    ? $e->getMessage()
                    : 'Failed to delete supplier: ' . $e->getMessage();

                $this->dispatch('toast', message: $message, type: 'error');
            }
        }
    }
}
