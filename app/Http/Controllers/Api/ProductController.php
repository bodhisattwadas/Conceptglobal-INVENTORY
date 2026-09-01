<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q') ?? $request->input('search');
        $companyId = $request->integer('company_id');
        $forPurchase = $request->boolean('for_purchase');

        $cacheKey = 'products_search_' . md5(json_encode([$query, $companyId, $forPurchase]));

        $products = Cache::remember($cacheKey, 300, function () use ($query, $companyId, $forPurchase) {
            return Product::query()
                ->with(['unit', 'company', 'inventoryStock'])
                ->where('is_active', true)
                ->when(! $forPurchase, fn ($builder) => $builder->whereHas('inventoryStock', fn ($stock) => $stock->where('quantity', '>', 0)))
                ->when($companyId, fn ($builder) => $builder->where('company_id', $companyId))
                ->when($query, function ($q) use ($query) {
                    $q->where(function ($searchQuery) use ($query) {
                        $searchQuery->where('name', 'like', "%{$query}%")
                            ->orWhere('sku', 'like', "%{$query}%");
                    });
                })
                ->limit(50)
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
                            'name' => $product->unit->name
                        ] : null,
                    ];
                });
        });

        return response()->json($products);
    }
}
