<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

class ProductController extends Controller
{
    public function print()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $storeName = Setting::get('store_name', config('app.name'));
        $storeAddress = Setting::get('store_address', '-');
        $storePhone = Setting::get('store_phone', '-');
        $storeEmail = Setting::get('store_email', '-');
        $totalProducts = Product::count();

        $products = Product::query()
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('units', 'products.unit_id', '=', 'units.id')
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.mrp',
                'products.min_stock',
                'products.is_active',
                'categories.name as category_name',
                'units.symbol as unit_symbol',
            ])
            ->orderBy('products.name')
            ->limit(100)
            ->get();

        $summary = [
            'totalProducts' => $totalProducts,
            'displayedProducts' => $products->count(),
        ];

        $filename = 'product-stock-report-'.now()->format('Y_m_d_His').'.pdf';

        return Pdf::setOption([
            'defaultFont' => 'Helvetica',
            'isFontSubsettingEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ])->loadView('products.print', [
            'products' => $products,
            'storeName' => $storeName,
            'storeAddress' => $storeAddress,
            'storePhone' => $storePhone,
            'storeEmail' => $storeEmail,
            'generatedAt' => now(),
            'reportCode' => 'PROD-'.now()->format('Ymd'),
            'summary' => $summary,
        ])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}
