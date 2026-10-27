<?php

namespace App\Livewire\Purchases;

use App\Enums\PurchaseLocation;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseException;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class PurchaseTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'purchase-table';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable('purchase_export_'.now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV)
                ->stripTags(true),

            PowerGrid::header()
                ->showSearchInput(),

            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Purchase::query()
            ->with(['supplier', 'company', 'creator']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('invoice_number', fn (Purchase $model) => $model->invoice_number ?: '-')
            ->add('supplier_name', fn (Purchase $model) => $model->supplier ? $model->supplier->name : '-')
            ->add('supplier_link', fn (Purchase $model) => $this->supplierLink($model))
            ->add('company_name', fn (Purchase $model) => $model->company ? ($model->company->short_name ?: $model->company->company_name) : '-')
            ->add('company_link', fn (Purchase $model) => $this->companyLink($model))
            ->add('location_badge', function (Purchase $model) {
                $location = $model->location instanceof PurchaseLocation ? $model->location : (PurchaseLocation::tryFrom((string) $model->location) ?? PurchaseLocation::SHOP);

                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border '.$location->color().'">'.$location->label().'</span>';
            })
            ->add('location_label', fn (Purchase $model) => $model->location instanceof PurchaseLocation ? $model->location->label() : ($model->location ? ucfirst((string) $model->location) : 'Shop'))
            ->add('purchase_date_formatted', fn (Purchase $model) => Carbon::parse($model->purchase_date)->format('d/m/Y'))
            ->add('total_formatted', fn (Purchase $model) => format_money((float) $model->total))
            ->add('status_badge', function (Purchase $model) {
                return view('components.status-badge', ['status' => $model->status])->render();
            })
            ->add('status_label', fn (Purchase $model) => $model->status instanceof PurchaseStatus ? $model->status->label() : ($model->status ? ucfirst((string) $model->status) : '-'))
            ->add('date_period', fn () => '') // Virtual field for filter
            ->add('creator_name', fn (Purchase $model) => $model->creator ? $model->creator->name : '-')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')->hidden(),

            Column::action(''),

            Column::make('PO Reference', 'invoice_number')
                ->searchable()
                ->sortable(),

            Column::make('Supplier / Vendor', 'supplier_link', 'supplier_id')
                ->searchable()
                ->sortable()
                ->visibleInExport(false),

            Column::make('Supplier / Vendor', 'supplier_name', 'supplier_id')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Brand / Company', 'company_link', 'company_id')
                ->searchable()
                ->sortable()
                ->visibleInExport(false),

            Column::make('Brand / Company', 'company_name', 'company_id')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Location', 'location_badge', 'location')
                ->sortable()
                ->visibleInExport(false),

            Column::make('Location', 'location_label', 'location')
                ->hidden()
                ->visibleInExport(true),

            Column::make('PO Date', 'purchase_date_formatted', 'purchase_date')
                ->sortable(),

            Column::make('Period', 'date_period')
                ->hidden()
                ->visibleInExport(false),

            Column::make('Total', 'total_formatted', 'total')
                ->sortable()
                ->headerAttribute('text-right')
                ->bodyAttribute('text-right'),

            Column::make('Status', 'status_badge', 'status')
                ->sortable()
                ->headerAttribute('text-center')
                ->bodyAttribute('text-center')
                ->visibleInExport(false),

            Column::make('Status', 'status_label', 'status')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Created By', 'creator_name', 'created_by')
                ->sortable(),
        ];
    }

    private function supplierLink(Purchase $purchase): string
    {
        $supplier = $purchase->supplier;

        if (! $supplier) {
            return '-';
        }

        $details = collect([
            $supplier->name,
            $supplier->contact_person ? 'Contact: '.$supplier->contact_person : null,
            $supplier->phone ? 'Phone: '.format_indian_phone($supplier->phone) : null,
            $supplier->email ? 'Email: '.$supplier->email : null,
            $supplier->tax_id ? 'GST / Tax ID: '.$supplier->tax_id : null,
        ])->filter()->join("\n");

        return sprintf(
            '<a href="%s" title="%s" class="font-medium text-blue-700 underline-offset-2 hover:text-blue-900 hover:underline">%s</a>',
            e(route('suppliers.show', $supplier)),
            e($details),
            e($supplier->name)
        );
    }

    private function companyLink(Purchase $purchase): string
    {
        $company = $purchase->company;

        if (! $company) {
            return '-';
        }

        $name = $company->short_name ?: $company->company_name;
        $details = collect([
            $company->company_name,
            $company->company_code ? 'Code: '.$company->company_code : null,
            $company->company_type ? 'Type: '.$company->company_type : null,
            $company->gstin ? 'GSTIN: '.$company->gstin : null,
            $company->phone ? 'Phone: '.format_indian_phone($company->phone) : null,
        ])->filter()->join("\n");

        return sprintf(
            '<a href="%s" title="%s" class="font-medium text-blue-700 underline-offset-2 hover:text-blue-900 hover:underline">%s</a>',
            e(route('companies.show', $company)),
            e($details),
            e($name)
        );
    }

    public function filters(): array
    {
        return [
            Filter::multiSelectAsync('supplier_name', 'supplier_id')
                ->url(route('ajax.suppliers.search'))
                ->method('POST')
                ->optionValue('value')
                ->optionLabel('text'),

            Filter::multiSelect('status', 'status')
                ->dataSource(collect(PurchaseStatus::cases())->map(fn ($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ]))
                ->optionLabel('label')
                ->optionValue('value'),

            Filter::select('location', 'location')
                ->dataSource([
                    ['name' => 'Shop', 'value' => 'shop'],
                    ['name' => 'Parlour', 'value' => 'parlour'],
                ])
                ->optionLabel('name')
                ->optionValue('value'),

            Filter::multiSelectAsync('creator_name', 'created_by')
                ->url(route('ajax.users.search'))
                ->method('POST')
                ->optionValue('value')
                ->optionLabel('text'),

            Filter::datepicker('purchase_date_formatted', 'purchase_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                    'altInput' => true,
                    'altFormat' => 'd/m/Y',
                ]),

            Filter::select('date_period')
                ->dataSource([
                    ['name' => 'Today', 'value' => 'today'],
                    ['name' => 'Yesterday', 'value' => 'yesterday'],
                    ['name' => 'This Week', 'value' => 'this_week'],
                    ['name' => 'Last Week', 'value' => 'last_week'],
                    ['name' => 'This Month', 'value' => 'this_month'],
                    ['name' => 'Last Month', 'value' => 'last_month'],
                ])
                ->optionLabel('name')
                ->optionValue('value')
                ->builder(function (Builder $query, string $value) {
                    switch ($value) {
                        case 'today':
                            $query->whereDate('purchase_date', now());
                            break;
                        case 'yesterday':
                            $query->whereDate('purchase_date', now()->subDay());
                            break;
                        case 'this_week':
                            $query->whereBetween('purchase_date', [now()->startOfWeek(), now()->endOfWeek()]);
                            break;
                        case 'last_week':
                            $query->whereBetween('purchase_date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);
                            break;
                        case 'this_month':
                            $query->whereMonth('purchase_date', now()->month)
                                ->whereYear('purchase_date', now()->year);
                            break;
                        case 'last_month':
                            $query->whereMonth('purchase_date', now()->subMonth()->month)
                                ->whereYear('purchase_date', now()->subMonth()->year);
                            break;
                    }
                }),
        ];
    }

    public function actions(Purchase $row): array
    {
        $actions = [];

        // View Action (Always visible)
        $actions[] = Button::add('view')
            ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>')
            ->class('bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md flex items-center justify-center')
            ->route('purchases.show', ['purchase' => $row->id])
            ->tooltip('View Details');

        // Edit Action (Draft, Ordered, or Received)
        if (in_array($row->status, [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED, PurchaseStatus::RECEIVED])) {
            $actions[] = Button::add('edit')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>')
                ->class('bg-amber-500 hover:bg-amber-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('purchases.edit', ['purchase' => $row->id])
                ->tooltip('Edit Purchase Order');
        }

        // Delete Action (Only Draft or Cancelled)
        if (in_array($row->status, [PurchaseStatus::DRAFT, PurchaseStatus::CANCELLED])) {
            $actions[] = Button::add('delete')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>')
                ->class('bg-red-500 hover:bg-red-600 text-white p-2 rounded-md flex items-center justify-center')
                ->dispatch('open-delete-modal', [
                    'component' => 'purchases.purchase-table',
                    'method' => 'delete',
                    'params' => ['rowId' => $row->id],
                    'title' => 'Delete Purchase Order?',
                    'description' => "Are you sure you want to delete PO '{$row->invoice_number}'? This action cannot be undone.",
                ])
                ->tooltip('Delete Purchase Order');
        }

        return $actions;
    }

    #[On('delete')]
    public function delete($rowId, PurchaseService $purchaseService): void
    {
        $purchase = Purchase::find($rowId);

        if ($purchase) {
            try {
                $purchaseService->deletePurchase($purchase);
                $this->dispatch('toast', message: 'Purchase order deleted successfully.', type: 'success');
            } catch (PurchaseException $e) {
                $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            } catch (\Exception $e) {
                $this->dispatch('toast', message: 'An unexpected error occurred during deletion.', type: 'error');
            }
        }
    }
}
