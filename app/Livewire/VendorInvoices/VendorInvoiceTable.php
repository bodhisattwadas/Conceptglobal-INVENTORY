<?php

namespace App\Livewire\VendorInvoices;

use App\Enums\VendorInvoiceStatus;
use App\Models\VendorInvoice;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class VendorInvoiceTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'vendor-invoice-table';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function setUp(): array
    {
        return [
            PowerGrid::exportable('vendor_invoice_export_' . now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return VendorInvoice::query()
            ->select('vendor_invoices.*')
            ->leftJoin('suppliers', 'vendor_invoices.supplier_id', '=', 'suppliers.id')
            ->with(['purchase', 'supplier', 'company', 'paidBy']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('po_reference_link', fn(VendorInvoice $model) => $this->poReferenceLink($model))
            ->add('invoice_number', fn(VendorInvoice $model) => $model->invoice_number ?: '<span class="italic text-gray-400">-</span>')
            ->add('supplier_link', fn(VendorInvoice $model) => $this->supplierLink($model))
            ->add('company_name', fn(VendorInvoice $model) => $model->company?->short_name ?: $model->company?->company_name ?: '-')
            ->add('amount_formatted', fn(VendorInvoice $model) => format_money($model->amount))
            ->add('vendor_additional_discount_formatted', fn(VendorInvoice $model) => $this->vendorDiscountLabel($model))
            ->add('paid_amount_badge', fn(VendorInvoice $model) => $this->amountBadge(format_money($model->paid_amount), 'paid'))
            ->add('due_amount_badge', fn(VendorInvoice $model) => $this->amountBadge(format_money(max(0, (int) $model->amount - (int) $model->paid_amount)), max(0, (int) $model->amount - (int) $model->paid_amount) > 0 ? 'due' : 'clear'))
            ->add('status_badge', fn(VendorInvoice $model) => view('components.status-badge', ['status' => $model->status])->render())
            ->add('document_link', fn(VendorInvoice $model) => $this->documentLink($model))
            ->add('payment_method_formatted', fn(VendorInvoice $model) => $model->payment_method ? str($model->payment_method)->replace('_', ' ')->title() : '-')
            ->add('payment_reference', fn(VendorInvoice $model) => $model->payment_reference ?: '-')
            ->add('paid_at_formatted', fn(VendorInvoice $model) => $model->paid_at?->format('d/m/Y h:i a') ?: '-')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::action(''),
            Column::make('PO Reference', 'po_reference_link', 'po_reference')->searchable()->sortable(),
            Column::make('Invoice Number', 'invoice_number')->searchable()->sortable(),
            Column::make('Supplier / Vendor', 'supplier_link', 'suppliers.name')->searchable()->sortable(),
            Column::make('Amount', 'amount_formatted', 'amount')->sortable()->headerAttribute('text-right')->bodyAttribute('text-right'),
            Column::make('Vendor Discount', 'vendor_additional_discount_formatted', 'vendor_additional_discount')->sortable()->headerAttribute('text-right')->bodyAttribute('text-right'),
            Column::make('Paid', 'paid_amount_badge', 'paid_amount')->sortable()->headerAttribute('text-right')->bodyAttribute('text-right'),
            Column::make('Due', 'due_amount_badge')->headerAttribute('text-right')->bodyAttribute('text-right'),
            Column::make('Status', 'status_badge', 'vendor_invoices.status')->sortable()->headerAttribute('text-center')->bodyAttribute('text-center'),
            Column::make('Paid At', 'paid_at_formatted', 'paid_at')->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'vendor_invoices.status')
                ->dataSource(collect(VendorInvoiceStatus::cases())->map(fn($status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ]))
                ->optionLabel('label')
                ->optionValue('value'),
        ];
    }

    public function actions(VendorInvoice $row): array
    {
        $actions = [
            Button::add('view')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>')
                ->class('bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md flex items-center justify-center')
                ->route('vendor-invoices.show', ['vendorInvoice' => $row->id])
                ->tooltip('View Details'),
        ];

        if (in_array($row->status, [VendorInvoiceStatus::UNPAID, VendorInvoiceStatus::PARTIALLY_PAID], true)) {
            $actions[] = Button::add('complete-payment')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>')
                ->class('bg-emerald-600 hover:bg-emerald-700 text-white p-2 rounded-md flex items-center justify-center')
                ->route('vendor-invoices.show', ['vendorInvoice' => $row->id])
                ->tooltip('Complete Payment');
        }

        return $actions;
    }

    private function documentLink(VendorInvoice $invoice): string
    {
        if (! $invoice->document_path) {
            return '<span class="italic text-gray-400">-</span>';
        }

        return sprintf(
            '<a href="%s" target="_blank" class="text-blue-700 underline-offset-2 hover:underline">Open</a>',
            e(public_storage_url($invoice->document_path))
        );
    }

    private function poReferenceLink(VendorInvoice $invoice): string
    {
        return sprintf(
            '<a href="%s" class="font-medium text-blue-700 underline-offset-2 hover:text-blue-900 hover:underline">%s</a>',
            e(route('purchases.show', $invoice->purchase_id)),
            e($invoice->po_reference)
        );
    }

    private function supplierLink(VendorInvoice $invoice): string
    {
        if (! $invoice->supplier) {
            return '-';
        }

        return sprintf(
            '<a href="%s" class="font-medium text-blue-700 underline-offset-2 hover:text-blue-900 hover:underline">%s</a>',
            e(route('suppliers.show', $invoice->supplier)),
            e($invoice->supplier->name)
        );
    }

    private function amountBadge(string $label, string $type): string
    {
        $class = match ($type) {
            'paid', 'clear' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'due' => 'border-red-200 bg-red-50 text-red-700',
            default => 'border-gray-200 bg-gray-50 text-gray-700',
        };

        return '<span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold '.$class.'">'.$label.'</span>';
    }

    private function vendorDiscountLabel(VendorInvoice $invoice): string
    {
        $discount = (float) $invoice->vendor_additional_discount;

        if ($discount == 0.0) {
            return '<span class="italic text-gray-400">-</span>';
        }

        $class = $discount > 0
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : 'border-red-200 bg-red-50 text-red-700';
        $label = ($discount > 0 ? '+ ' : '') . format_money($discount);

        return '<span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold '.$class.'">'.e($label).'</span>';
    }
}
