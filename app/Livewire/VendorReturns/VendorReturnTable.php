<?php

namespace App\Livewire\VendorReturns;

use App\Models\VendorReturn;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class VendorReturnTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'vendor-return-table';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function setUp(): array
    {
        return [
            PowerGrid::exportable('vendor_returns_'.now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return VendorReturn::query()
            ->select('vendor_returns.*')
            ->leftJoin('suppliers', 'vendor_returns.supplier_id', '=', 'suppliers.id')
            ->leftJoin('purchases', 'vendor_returns.purchase_id', '=', 'purchases.id')
            ->with(['purchase', 'supplier', 'company', 'creator']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('return_number_link', fn (VendorReturn $model) => '<a href="'.route('vendor-returns.show', $model).'" class="font-bold text-rose-600 hover:text-rose-900 hover:underline">'.e($model->return_number).'</a>')
            ->add('return_date_formatted', fn (VendorReturn $model) => $model->return_date ? $model->return_date->format('d M Y') : '-')
            ->add('supplier_link', fn (VendorReturn $model) => $model->supplier ? '<a href="'.route('suppliers.show', $model->supplier).'" class="text-indigo-600 hover:underline">'.e($model->supplier->name).'</a>' : '-')
            ->add('po_reference_link', fn (VendorReturn $model) => $model->purchase ? '<a href="'.route('purchases.show', $model->purchase).'" class="text-gray-700 hover:underline font-mono text-xs">'.e($model->purchase->invoice_number ?: 'PO #'.$model->purchase->id).'</a>' : '-')
            ->add('reason_badge', fn (VendorReturn $model) => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800">'.e($model->reason_label).'</span>')
            ->add('total_amount_formatted', fn (VendorReturn $model) => '<span class="font-bold text-rose-700">'.format_money($model->total_amount).'</span>')
            ->add('status_badge', fn (VendorReturn $model) => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-800">Completed</span>')
            ->add('created_by_name', fn (VendorReturn $model) => e($model->creator?->name ?: '-'));
    }

    public function columns(): array
    {
        return [
            Column::make('Return #', 'return_number_link', 'return_number')
                ->sortable()
                ->searchable(),

            Column::make('Date', 'return_date_formatted', 'return_date')
                ->sortable(),

            Column::make('Supplier / Vendor', 'supplier_link', 'suppliers.name')
                ->sortable()
                ->searchable(),

            Column::make('Purchase Order', 'po_reference_link', 'purchases.invoice_number')
                ->sortable()
                ->searchable(),

            Column::make('Reason', 'reason_badge', 'reason')
                ->sortable(),

            Column::make('Total Returned', 'total_amount_formatted', 'total_amount')
                ->sortable(),

            Column::make('Status', 'status_badge', 'status')
                ->sortable(),

            Column::action('Action'),
        ];
    }

    public function actions(VendorReturn $row): array
    {
        return [
            Button::add('view')
                ->slot('<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>')
                ->class('p-1 text-gray-500 hover:text-indigo-600 rounded transition-colors')
                ->attributes(['title' => 'View Vendor Return'])
                ->route('vendor-returns.show', ['vendorReturn' => $row->id]),

            Button::add('print')
                ->slot('<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>')
                ->class('p-1 text-gray-500 hover:text-rose-600 rounded transition-colors')
                ->attributes(['title' => 'Print Debit Note', 'target' => '_blank'])
                ->route('vendor-returns.print', ['vendorReturn' => $row->id]),
        ];
    }
}
