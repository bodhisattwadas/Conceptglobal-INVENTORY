<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', $request->input('search', ''));
        $supplierId = $request->integer('supplier_id');

        $companies = Company::query()
            ->active()
            ->when($supplierId, fn ($builder) => $builder->whereHas(
                'suppliers',
                fn ($supplierQuery) => $supplierQuery->where('suppliers.id', $supplierId)
            ))
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($subQuery) use ($query) {
                    $subQuery->where('company_code', 'like', "%{$query}%")
                        ->orWhere('company_name', 'like', "%{$query}%")
                        ->orWhere('legal_name', 'like', "%{$query}%");
                });
            })
            ->orderBy('company_name')
            ->limit(20)
            ->get(['id', 'company_code', 'company_name', 'short_name']);

        return response()->json($companies->map(fn (Company $company) => [
            'value' => $company->id,
            'id' => $company->id,
            'text' => "{$company->company_code} : ".($company->short_name ?: $company->company_name),
        ]));
    }
}
