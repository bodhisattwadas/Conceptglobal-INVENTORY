<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

class InventoryController extends Controller
{
    public function print()
    {
        $storeName = Setting::get('store_name', config('app.name'));
        $storeAddress = Setting::get('store_address', '-');
        $storePhone = Setting::get('store_phone', '-');
        $storeEmail = Setting::get('store_email', '-');

        $stocks = InventoryStock::query()
            ->with(['product.company', 'product.category', 'product.unit'])
            ->whereHas('product')
            ->orderByDesc('updated_at')
            ->get();

        $filename = 'inventory-stock-report-'.now()->format('Y_m_d_His').'.pdf';

        return Pdf::loadView('inventory.print', [
            'stocks' => $stocks,
            'storeName' => $storeName,
            'storeAddress' => $storeAddress,
            'storePhone' => $storePhone,
            'storeEmail' => $storeEmail,
            'generatedAt' => now(),
            'reportCode' => 'INV-'.now()->format('Ymd'),
            'summary' => [
                'totalProducts' => $stocks->count(),
                'lowStockCount' => $stocks->filter(function (InventoryStock $stock) {
                    $minStock = (int) ($stock->product?->min_stock ?? 0);

                    return $stock->quantity <= $minStock;
                })->count(),
                'inStockCount' => $stocks->filter(function (InventoryStock $stock) {
                    $minStock = (int) ($stock->product?->min_stock ?? 0);

                    return $stock->quantity > $minStock;
                })->count(),
            ],
        ])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}