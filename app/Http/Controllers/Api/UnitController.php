<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');

        $units = Unit::query()
            ->when($query, function ($builder) use ($query) {
                $builder->where(function ($search) use ($query) {
                    $search->where('name', 'like', "%{$query}%")
                        ->orWhere('symbol', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'symbol'])
            ->map(fn (Unit $unit) => [
                'value' => $unit->id,
                'text' => "{$unit->name} ({$unit->symbol})",
            ]);

        return response()->json($units);
    }
}
