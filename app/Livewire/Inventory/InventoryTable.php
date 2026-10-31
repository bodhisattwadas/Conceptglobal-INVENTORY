<?php

namespace App\Livewire\Inventory;

use App\Enums\PurchaseLocation;
use App\Models\InventoryStock;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class InventoryTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'inventory-table';

    public string $sortField = 'inventory_stocks.updated_at';

    public string $sortDirection = 'desc';

    public function setUp(): array
    {
        return [
            PowerGrid::exportable('inventory_export_'.now()->format('Y_m_d_His'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage(perPage: 10, perPageValues: [10, 25, 50, 100])
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return InventoryStock::query()
            ->leftJoin('products', 'inventory_stocks.product_id', '=', 'products.id')
            ->leftJoin('companies', 'products.company_id', '=', 'companies.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select([
                'inventory_stocks.*',
                'products.sku as sku',
                'products.product_code as product_code',
                'products.name as product_name',
                'companies.company_name as company_name',
                'companies.short_name as brand_short_name',
                'categories.name as category_name',
            ])
            ->with(['product.company', 'product.unit', 'product.category', 'product.purchaseItems.purchase']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('action_buttons', fn (InventoryStock $model) => $this->actionButtons($model))
            ->add('sku', fn (InventoryStock $model) => $model->product?->sku ?: '-')
            ->add('product_code', fn (InventoryStock $model) => $model->product?->product_code ?: '-')
            ->add('product_cell', fn (InventoryStock $model) => $this->productCell($model))
            ->add('product_name', fn (InventoryStock $model) => $model->product?->name ?: '-')
            ->add('brand_name', fn (InventoryStock $model) => $model->product?->company?->short_name ?: $model->product?->company?->company_name ?: '-')
            ->add('category_name', fn (InventoryStock $model) => $model->product?->category?->name ?: '-')
            ->add('location_badges', fn (InventoryStock $model) => $this->locationBadges($model))
            ->add('location_export', fn (InventoryStock $model) => $this->locationExport($model))
            ->add('quantity')
            ->add('quantity_export', fn (InventoryStock $model) => (string) (int) $model->quantity)
            ->add('quantity_with_unit', fn (InventoryStock $model) => $this->numberWithUnit((int) $model->quantity, $model))
            ->add('batch_quantities', fn (InventoryStock $model) => $this->batchQuantities($model))
            ->add('unit', fn (InventoryStock $model) => $model->product?->unit?->symbol ?: $model->product?->unit?->name ?: '-')
            ->add('min_stock', fn (InventoryStock $model) => $model->product?->min_stock ?? 0)
            ->add('min_stock_export', fn (InventoryStock $model) => (string) (int) ($model->product?->min_stock ?? 0))
            ->add('min_stock_with_unit', fn (InventoryStock $model) => $this->numberWithUnit((int) ($model->product?->min_stock ?? 0), $model))
            ->add('stock_badge', fn (InventoryStock $model) => $this->stockBadge($model))
            ->add('stock_status_export', fn (InventoryStock $model) => $model->quantity <= (int) ($model->product?->min_stock ?? 0) ? 'Low Stock' : 'In Stock')
            ->add('updated_at');
    }

    public function columns(): array
    {
        return [
            Column::make('Action', 'action_buttons')->visibleInExport(false),
            Column::make('SKU', 'sku', 'products.sku')->searchable()->sortable()->headerAttribute('text-center'),
            Column::make('Product Code', 'product_code', 'products.product_code')->searchable()->sortable()->headerAttribute('text-center'),
            Column::make('Product', 'product_cell', 'products.name')->searchable()->sortable()->visibleInExport(false),
            Column::make('Product', 'product_name', 'products.name')->hidden()->visibleInExport(true),
            Column::make('Brand / Company', 'brand_name', 'companies.company_name')->searchable()->sortable()->hidden()->visibleInExport(true),
            Column::make('Category', 'category_name', 'categories.name')->searchable()->sortable()->hidden()->visibleInExport(true),
            Column::make('Location', 'location_export')->hidden()->visibleInExport(true),
            Column::make('Quantity', 'quantity_with_unit', 'quantity')->sortable()->headerAttribute('text-center')->bodyAttribute('text-center')->visibleInExport(false),
            Column::make('Quantity', 'quantity_export', 'quantity')->hidden()->visibleInExport(true),
            Column::make('Batch Quantity', 'batch_quantities')->headerAttribute('text-center')->bodyAttribute('text-center')->visibleInExport(false),
            Column::make('Min Quantity', 'min_stock_with_unit', 'min_stock')->headerAttribute('text-center')->bodyAttribute('text-center')->visibleInExport(false),
            Column::make('Min Quantity', 'min_stock_export', 'min_stock')->hidden()->visibleInExport(true),
            Column::make('Status', 'stock_badge')->headerAttribute('text-center')->bodyAttribute('text-center')->visibleInExport(false),
            Column::make('Status', 'stock_status_export')->hidden()->visibleInExport(true),
        ];
    }

    public function actionButtons(InventoryStock $row): string
    {
        if (! $row->product_id) {
            return '';
        }

        $url = route('inventory.show', ['inventoryStock' => $row->id]);

        return '<a href="'.e($url).'" title="View Product" class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-blue-500 text-white hover:bg-blue-600">'
            .'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>'
            .'</a>';
    }

    public function stockBadge(InventoryStock $stock): string
    {
        $minStock = (int) ($stock->product?->min_stock ?? 0);
        $low = $stock->quantity <= $minStock;
        $class = $low
            ? 'border-red-200 bg-red-50 text-red-700'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700';
        $label = $low ? 'Low Stock' : 'In Stock';

        return '<span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold '.$class.'">'.$label.'</span>';
    }

    public function batchQuantities(InventoryStock $stock): string
    {
        $batches = $stock->product?->purchaseItems
            ?->filter(fn ($item) => (int) ($item->received_quantity ?? 0) > 0)
            ->sortBy(fn ($item) => [
                $item->purchase?->purchase_date?->timestamp ?? PHP_INT_MAX,
                $item->id,
            ])
            ->values() ?? collect();

        if ($batches->isEmpty()) {
            return '<span class="text-xs text-gray-400">-</span>';
        }

        $colors = [
            'border-blue-300 bg-blue-50 text-blue-800',
            'border-emerald-300 bg-emerald-50 text-emerald-800',
            'border-amber-300 bg-amber-50 text-amber-800',
            'border-fuchsia-300 bg-fuchsia-50 text-fuchsia-800',
            'border-cyan-300 bg-cyan-50 text-cyan-800',
            'border-rose-300 bg-rose-50 text-rose-800',
            'border-violet-300 bg-violet-50 text-violet-800',
            'border-lime-300 bg-lime-50 text-lime-800',
        ];

        $html = '<div class="flex flex-col items-center gap-1 max-w-xs mx-auto">';

        foreach ($batches as $index => $batch) {
            $loc = $batch->isParlour() ? 'Parlour' : 'Shop';
            $label = ($batch->batch_number ?: 'Batch').' : '.(int) $batch->received_quantity.' @ '.format_money($batch->unit_price).' ['.$loc.']';
            $class = $colors[$index % count($colors)];
            $locTagClass = $batch->isParlour() ? 'text-purple-700 bg-purple-100/70' : 'text-blue-700 bg-blue-100/70';

            $html .= '<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 font-mono text-[10px] font-bold leading-tight shadow-xs '.$class.'" title="'.e($label).'">'
                .'<span class="truncate pr-1.5 text-left max-w-36" style="max-width: 7.5rem;" title="'.e($batch->batch_number ?: 'Batch').'">'.e($batch->batch_number ?: 'Batch').'</span>'
                .'<span class="shrink-0 border-l border-current/25 px-1.5 text-center">'.e((string) (int) $batch->received_quantity).'</span>'
                .'<span class="shrink-0 border-l border-current/25 px-1.5 text-right whitespace-nowrap">'.e(format_money($batch->unit_price)).'</span>'
                .'<span class="shrink-0 border-l border-current/25 pl-1.5 text-[9px] uppercase font-bold '.e($locTagClass).' rounded px-1">'.e($loc).'</span>'
                .'</span>';
        }

        $html .= '</div>';

        return $html;
    }

    public function productCell(InventoryStock $model): string
    {
        $name = $model->product?->name ?: $model->product_name ?: '-';
        $brand = $model->product?->company?->short_name ?: $model->product?->company?->company_name ?: ($model->brand_short_name ?: $model->company_name ?: null);
        $category = $model->product?->category?->name ?: $model->category_name ?: null;

        $badges = [];

        // Brand Badge
        if ($brand && $brand !== '-') {
            $badges[] = '<span class="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700" title="Brand: '.e($brand).'">'
                .'<svg class="h-3 w-3 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>'
                .'<span class="truncate max-w-40">'.e($brand).'</span>'
                .'</span>';
        }

        // Category Badge
        if ($category && $category !== '-') {
            $badges[] = '<span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700" title="Category: '.e($category).'">'
                .'<svg class="h-3 w-3 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-6A1.125 1.125 0 0 1 2.25 9.375v-2.25ZM2.25 14.625c0-.621.504-1.125 1.125-1.125h6c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-2.25ZM13.5 7.125c0-.621.504-1.125 1.125-1.125h6c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-6A1.125 1.125 0 0 1 13.5 9.375v-2.25ZM13.5 14.625c0-.621.504-1.125 1.125-1.125h6c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" /></svg>'
                .'<span class="truncate max-w-40">'.e($category).'</span>'
                .'</span>';
        }

        // Location Badges (Shop, Parlour)
        foreach ($this->locationPills($model) as $locPill) {
            $badges[] = $locPill;
        }

        $html = '<div class="flex flex-col gap-1.5 py-1 text-left min-w-48">';
        $html .= '<span class="font-medium text-gray-900 leading-snug">'.e($name).'</span>';

        if (! empty($badges)) {
            $html .= '<div class="flex flex-wrap items-center gap-1.5 mt-0.5">'.implode('', $badges).'</div>';
        }

        $html .= '</div>';

        return $html;
    }

    public function locationPills(InventoryStock $stock): array
    {
        $product = $stock->product;
        $activeBatches = $product?->purchaseItems
            ?->filter(fn ($item) => (int) ($item->received_quantity ?? 0) > 0) ?? collect();

        $shopQty = 0;
        $parlourQty = 0;
        $hasShop = false;
        $hasParlour = false;

        foreach ($activeBatches as $batch) {
            if ($batch->isParlour()) {
                $hasParlour = true;
                $parlourQty += (int) $batch->received_quantity;
            } else {
                $hasShop = true;
                $shopQty += (int) $batch->received_quantity;
            }
        }

        if (! $hasShop && ! $hasParlour) {
            if ($product?->location === PurchaseLocation::PARLOUR) {
                $hasParlour = true;
            } else {
                $hasShop = true;
            }
        }

        $pills = [];

        if ($hasShop) {
            $shopText = 'Shop';
            if ($shopQty > 0 && $hasParlour) {
                $shopText .= ' ('.$shopQty.')';
            }
            $pills[] = '<span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700" title="Shop Quantity: '.$shopQty.'">'
                .'<span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>'
                .e($shopText)
                .'</span>';
        }

        if ($hasParlour) {
            $parlourText = 'Parlour';
            if ($parlourQty > 0 && $hasShop) {
                $parlourText .= ' ('.$parlourQty.')';
            }
            $pills[] = '<span class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-xs font-semibold text-purple-700" title="Parlour Quantity: '.$parlourQty.'">'
                .'<span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>'
                .e($parlourText)
                .'</span>';
        }

        return $pills;
    }

    public function locationBadges(InventoryStock $stock): string
    {
        return '<div class="flex items-center justify-center gap-1.5 flex-wrap">'.implode('', $this->locationPills($stock)).'</div>';
    }

    public function locationExport(InventoryStock $stock): string
    {
        $product = $stock->product;
        $activeBatches = $product?->purchaseItems
            ?->filter(fn ($item) => (int) ($item->received_quantity ?? 0) > 0) ?? collect();

        $hasShop = false;
        $hasParlour = false;

        foreach ($activeBatches as $batch) {
            if ($batch->isParlour()) {
                $hasParlour = true;
            } else {
                $hasShop = true;
            }
        }

        if (! $hasShop && ! $hasParlour) {
            return $product?->location === PurchaseLocation::PARLOUR ? 'Parlour' : 'Shop';
        }

        if ($hasShop && $hasParlour) {
            return 'Shop, Parlour';
        }

        return $hasParlour ? 'Parlour' : 'Shop';
    }

    public function numberWithUnit(int $number, InventoryStock $stock): string
    {
        $unit = $stock->product?->unit?->symbol ?: $stock->product?->unit?->name ?: '';

        $html = '<div class="flex flex-col items-center leading-tight">';
        $html .= '<span class="text-sm font-semibold text-gray-900">'.e((string) $number).'</span>';

        if ($unit !== '') {
            $html .= '<span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500">'.e($unit).'</span>';
        }

        $html .= '</div>';

        return $html;
    }
}
