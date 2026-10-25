<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $rawQuery = $request->input('q') ?? $request->input('search');
        $query = is_string($rawQuery) ? trim($rawQuery) : '';
        $companyId = $request->integer('company_id');
        $supplierId = $request->integer('supplier_id');
        $forPurchase = $request->boolean('for_purchase');

        $queryLower = mb_strtolower($query);

        $cacheKey = 'products_search_'.md5(json_encode([$queryLower, $companyId, $supplierId, $forPurchase]));

        $products = Cache::remember($cacheKey, 60, function () use ($query, $queryLower, $companyId, $supplierId, $forPurchase) {
            $supplierCompanyIds = [];
            if ($supplierId > 0 && ! $companyId) {
                $supplierCompanyIds = DB::table('supplier_companies')
                    ->where('supplier_id', $supplierId)
                    ->pluck('company_id')
                    ->all();
            }

            return Product::query()
                ->with(['unit', 'company', 'inventoryStock'])
                ->where('is_active', true)
                ->when(! $forPurchase, fn ($builder) => $builder->whereHas('inventoryStock', fn ($stock) => $stock->where('quantity', '>', 0)))
                ->when($companyId > 0, fn ($builder) => $builder->where('company_id', $companyId))
                ->when(! $companyId && ! empty($supplierCompanyIds), fn ($builder) => $builder->whereIn('company_id', $supplierCompanyIds))
                ->when($query !== '', function ($q) use ($queryLower) {
                    $q->where(function ($searchQuery) use ($queryLower) {
                        $searchQuery->whereRaw('LOWER(name) LIKE ?', ["%{$queryLower}%"])
                            ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$queryLower}%"]);
                    });
                })
                ->when($query !== '', function ($q) use ($queryLower) {
                    $q->orderByRaw('CASE 
                        WHEN LOWER(sku) = ? THEN 0 
                        WHEN LOWER(sku) LIKE ? THEN 1 
                        WHEN LOWER(name) = ? THEN 2 
                        WHEN LOWER(name) LIKE ? THEN 3 
                        ELSE 4 
                    END', [
                        $queryLower,
                        $queryLower.'%',
                        $queryLower,
                        $queryLower.'%',
                    ]);
                })
                ->orderBy('name')
                ->limit(($companyId > 0 || $forPurchase) ? 300 : 50)
                ->get()
                ->map(function ($product) {
                    return [
                        'value' => $product->id,
                        'id' => $product->id,
                        'text' => $product->name,
                        'name' => $product->name,
                        'price' => $product->mrp ?: $product->purchase_price,
                        'selling_price' => $product->mrp ?: $product->selling_price,
                        'mrp' => $product->mrp,
                        'sku' => $product->sku,
                        'brand' => $product->company ? ($product->company->short_name ?: $product->company->company_name) : null,
                        'brand_id' => $product->company_id,
                        'quantity' => $product->inventoryStock?->quantity ?? 0,
                        'unit' => $product->unit ? [
                            'symbol' => $product->unit->symbol,
                            'name' => $product->unit->name,
                        ] : null,
                    ];
                });
        });

        return response()->json($products);
    }
}
