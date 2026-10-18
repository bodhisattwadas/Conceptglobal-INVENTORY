<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CustomerData;
use App\Exceptions\CustomerException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q') ?? $request->input('search');
        $cacheKey = 'customers_search_'.md5($query);

        $customers = Cache::remember($cacheKey, 300, function () use ($query) {
            return Customer::query()
                ->when($query, function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->limit(20)
                ->get()
                ->map(function ($customer) {
                    return [
                        'value' => $customer->id,
                        'text' => $customer->name.($customer->phone ? ' | '.format_indian_phone($customer->phone) : ''),
                        'name' => $customer->name,
                        'phone' => $customer->phone ? format_indian_phone($customer->phone) : null,
                        'raw_phone' => $customer->phone,
                        'email' => $customer->email,
                        'address' => $customer->address,
                        'notes' => $customer->notes,
                    ];
                });
        });

        return response()->json($customers);
    }

    public function byPhone(Request $request)
    {
        $phone = trim((string) ($request->input('phone') ?? $request->input('q') ?? ''));

        if (blank($phone)) {
            return response()->json(['found' => false, 'customer' => null]);
        }

        $digits = preg_replace('/\D+/', '', $phone);
        $formatted = normalize_indian_phone($phone);

        $customer = Customer::query()
            ->where(function ($q) use ($phone, $digits, $formatted) {
                $q->where('phone', $phone);

                if ($formatted) {
                    $q->orWhere('phone', $formatted);
                }

                if (! empty($digits)) {
                    $q->orWhere('phone', 'like', "%{$digits}%");

                    if (strlen($digits) >= 10) {
                        $last10 = substr($digits, -10);
                        $part1 = substr($last10, 0, 5);
                        $part2 = substr($last10, 5);
                        $q->orWhere('phone', 'like', "%{$part1}%{$part2}%");
                    }
                }
            })
            ->first();

        if ($customer) {
            return response()->json([
                'found' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone ? format_indian_phone($customer->phone) : $phone,
                    'raw_phone' => $customer->phone,
                    'email' => $customer->email,
                    'address' => $customer->address,
                    'notes' => $customer->notes,
                ],
            ]);
        }

        return response()->json([
            'found' => false,
            'customer' => null,
        ]);
    }

    public function store(Request $request, CustomerService $customerService)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => ['nullable', 'string', 'max:20', 'regex:/^\s*(?:\+91|91|0)?[\s-]*[6-9]\d{4}[\s-]*\d{5}\s*$/'],
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:500',
                'notes' => 'nullable|string|max:500',
            ]);

            $data = array_merge([
                'phone' => null,
                'email' => null,
                'address' => null,
                'notes' => null,
            ], $validated);

            $customerData = CustomerData::fromArray($data);
            $customer = $customerService->createCustomer($customerData);

            return response()->json($customer, 201);

        } catch (CustomerException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create customer: '.$e->getMessage()], 500);
        }
    }
}
