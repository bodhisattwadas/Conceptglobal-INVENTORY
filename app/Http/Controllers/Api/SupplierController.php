<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SupplierController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q') ?? $request->input('query') ?? $request->input('search');
        $companyInput = $request->input('company_id') ?: $request->input('brand_id');

        $companyIds = [];
        if (is_array($companyInput)) {
            $companyIds = array_filter(array_map('intval', $companyInput));
        } elseif (is_numeric($companyInput) && (int) $companyInput > 0) {
            $companyIds = [(int) $companyInput];
        }

        $cacheKey = 'suppliers_search_'.md5(($query ?? '').'_'.implode(',', $companyIds));

        $suppliers = Cache::remember($cacheKey, 60, function () use ($query, $companyIds) {
            return Supplier::query()
                ->when(! empty($companyIds), function ($q) use ($companyIds) {
                    $q->whereHas('companies', function ($cq) use ($companyIds) {
                        $cq->whereIn('companies.id', $companyIds);
                    });
                })
                ->when(! empty($query), function ($q) use ($query) {
                    $q->where(function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%");
                    });
                })
                ->limit(30)
                ->get()
                ->map(function ($supplier) {
                    return [
                        'value' => $supplier->id,
                        'text' => $supplier->name.($supplier->phone ? ' | '.format_indian_phone($supplier->phone) : ''),
                    ];
                });
        });

        return response()->json($suppliers);
    }
}
