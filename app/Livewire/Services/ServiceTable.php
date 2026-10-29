<?php

namespace App\Livewire\Services;

use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class ServiceTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'service-table';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable('services_export_'.now()->format('Y_m_d'))
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
        return Service::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name', fn (Service $model) => '<div class="font-semibold text-gray-900">'.e($model->name).'</div>'.($model->description ? '<div class="text-xs text-gray-500 truncate max-w-xs">'.e($model->description).'</div>' : ''))
            ->add('code', fn (Service $model) => $model->code ? '<span class="font-mono text-xs font-bold tracking-wide text-gray-800 bg-gray-100 px-2 py-0.5 rounded border border-gray-200">'.e($model->code).'</span>' : '<span class="text-gray-400">-</span>')
            ->add('cost_formatted', fn (Service $model) => '<span class="font-bold text-indigo-700">'.format_money($model->cost).'</span>')
            ->add('is_active_badge', function (Service $model) {
                return $model->is_active
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Inactive</span>';
            })
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->hidden()
                ->visibleInExport(true),

            Column::make('Service Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Service Code', 'code')
                ->sortable()
                ->searchable(),

            Column::make('Cost / Rate', 'cost_formatted', 'cost')
                ->sortable(),

            Column::make('Status', 'is_active_badge', 'is_active')
                ->sortable()
                ->headerAttribute('text-center')
                ->bodyAttribute('text-center'),

            Column::action('Action'),
        ];
    }

    public function actions(Service $row): array
    {
        return [
            Button::add('view')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>')
                ->class('bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md flex items-center justify-center')
                ->dispatch('show-service', ['service' => $row->id])
                ->tooltip('View Service'),

            Button::add('edit')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>')
                ->class('bg-amber-500 hover:bg-amber-600 text-white p-2 rounded-md flex items-center justify-center')
                ->dispatch('edit-service', ['service' => $row->id])
                ->tooltip('Edit Service'),

            Button::add('delete')
                ->slot('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>')
                ->class('bg-red-500 hover:bg-red-600 text-white p-2 rounded-md flex items-center justify-center')
                ->dispatch('open-delete-modal', [
                    'component' => 'services.service-table',
                    'method' => 'delete',
                    'params' => ['rowId' => $row->id],
                    'title' => 'Delete Service?',
                    'description' => "Are you sure you want to delete service '{$row->name}'? This action cannot be undone.",
                ])
                ->tooltip('Delete Service'),
        ];
    }

    #[On('delete')]
    public function delete($rowId, ServiceService $service): void
    {
        $item = Service::find($rowId);

        if ($item) {
            try {
                $service->deleteService($item);
                $this->dispatch('toast', message: 'Service deleted successfully.', type: 'success');
            } catch (\Exception $e) {
                $this->dispatch('toast', message: $e->getMessage() ?: 'An unexpected error occurred.', type: 'error');
            }
        }
    }
}
