<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
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
        $allStock = $request->boolean('all_stock') || $forPurchase;

        $queryLower = mb_strtolower($query);

        $supplierCompanyIds = [];
        if ($supplierId > 0 && ! $companyId) {
            $supplierCompanyIds = DB::table('supplier_companies')
                ->where('supplier_id', $supplierId)
                ->pluck('company_id')
                ->all();
        }

        $products = Product::query()
            ->with(['unit', 'company', 'inventoryStock'])
            ->where('is_active', true)
            ->when(! $allStock, fn ($builder) => $builder->whereHas('inventoryStock', fn ($stock) => $stock->where('quantity', '>', 0)))
            ->when($companyId > 0, fn ($builder) => $builder->where('company_id', $companyId))
            ->when($supplierId > 0 && ! $companyId, fn ($builder) => $builder->whereIn('company_id', $supplierCompanyIds))
            ->when($query !== '', function ($q) use ($queryLower) {
                $terms = preg_split('/[\s\-–—]+/u', $queryLower, -1, PREG_SPLIT_NO_EMPTY);
                if (empty($terms)) {
                    $terms = [$queryLower];
                }

                $q->where(function ($sub) use ($queryLower, $terms) {
                    $sub->where(function ($allTerms) use ($terms) {
                        foreach ($terms as $term) {
                            $allTerms->where(function ($termQuery) use ($term) {
                                $termQuery->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$term}%"])
                                    ->orWhereRaw('LOWER(product_code) LIKE ?', ["%{$term}%"])
                                    ->orWhereHas('company', function ($companyQuery) use ($term) {
                                        $companyQuery->whereRaw('LOWER(company_name) LIKE ?', ["%{$term}%"])
                                            ->orWhereRaw('LOWER(short_name) LIKE ?', ["%{$term}%"]);
                                    });
                            });
                        }
                    })
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$queryLower}%"])
                        ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$queryLower}%"])
                        ->orWhereRaw('LOWER(product_code) LIKE ?', ["%{$queryLower}%"]);
                });
            })
            ->when($query !== '', function ($q) use ($queryLower) {
                $firstTerm = explode(' ', trim($queryLower))[0] ?? $queryLower;
                $q->orderByRaw('CASE 
                    WHEN LOWER(product_code) = ? THEN 0 
                    WHEN LOWER(product_code) LIKE ? THEN 1 
                    WHEN LOWER(product_code) = ? THEN 2 
                    WHEN LOWER(product_code) LIKE ? THEN 3 
                    WHEN LOWER(sku) = ? THEN 4 
                    WHEN LOWER(sku) LIKE ? THEN 5 
                    WHEN LOWER(name) = ? THEN 6 
                    WHEN LOWER(name) LIKE ? THEN 7 
                    ELSE 8 
                END', [
                    $queryLower,
                    $queryLower.'%',
                    $firstTerm,
                    $firstTerm.'%',
                    $queryLower,
                    $queryLower.'%',
                    $queryLower,
                    $queryLower.'%',
                ]);
            })
            ->orderBy('name')
            ->limit(($companyId > 0 || $allStock) ? 300 : 50)
            ->get()
            ->map(function ($product) {
                $displayText = $product->product_code
                    ? "{$product->product_code} - {$product->name}"
                    : ($product->sku ? "{$product->sku} - {$product->name}" : $product->name);

                $sellingPrice = (float) ($product->selling_price ?: ($product->mrp ?: $product->purchase_price ?: 0));
                $mrp = (float) ($product->mrp ?: ($product->selling_price ?: 0));

                return [
                    'value' => $product->id,
                    'id' => $product->id,
                    'text' => $displayText,
                    'name' => $product->name,
                    'price' => $sellingPrice,
                    'selling_price' => $sellingPrice,
                    'mrp' => $mrp,
                    'sku' => $product->sku,
                    'product_code' => $product->product_code,
                    'brand' => $product->company ? ($product->company->short_name ?: $product->company->company_name) : null,
                    'brand_id' => $product->company_id,
                    'quantity' => $product->inventoryStock?->quantity ?? 0,
                    'unit' => $product->unit ? [
                        'symbol' => $product->unit->symbol,
                        'name' => $product->unit->name,
                    ] : null,
                ];
            });

        return response()->json($products);
    }
}
