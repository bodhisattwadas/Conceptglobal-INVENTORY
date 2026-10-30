<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function search(Request $request)
    {
        $rawQuery = $request->input('q') ?? $request->input('search') ?? $request->input('query');
        $query = is_string($rawQuery) ? trim($rawQuery) : '';
        $queryLower = mb_strtolower($query);

        $services = Service::query()
            ->where('is_active', true)
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
                                    ->orWhereRaw('LOWER(code) LIKE ?', ["%{$term}%"])
                                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$term}%"]);
                            });
                        }
                    })
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$queryLower}%"])
                        ->orWhereRaw('LOWER(code) LIKE ?', ["%{$queryLower}%"]);
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function ($service) {
                $cost = (float) $service->cost;
                $displayText = $service->code
                    ? "{$service->name} ({$service->code})"
                    : $service->name;

                return [
                    'value' => $service->id,
                    'id' => $service->id,
                    'text' => $displayText,
                    'name' => $service->name,
                    'price' => $cost,
                    'selling_price' => $cost,
                    'cost' => $cost,
                    'code' => $service->code,
                    'sku' => $service->code,
                    'is_service' => true,
                    'description' => $service->description,
                    'quantity' => 9999, // services don't have stock limit
                    'unit' => [
                        'symbol' => 'srv',
                        'name' => 'Service',
                    ],
                ];
            });

        return response()->json($services);
    }
}
