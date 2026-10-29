<?php

namespace App\Services;

use App\DTOs\ServiceData;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    /**
     * Create a new service.
     */
    public function createService(ServiceData $data): Service
    {
        return DB::transaction(function () use ($data) {
            $code = $data->code;
            if (empty($code)) {
                $count = Service::count() + 1;
                $code = 'SRV-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            }

            return Service::create([
                'name' => $data->name,
                'code' => $code,
                'cost' => $data->cost,
                'description' => $data->description,
                'is_active' => $data->is_active,
            ]);
        });
    }

    /**
     * Update an existing service.
     */
    public function updateService(Service $service, ServiceData $data): Service
    {
        return DB::transaction(function () use ($service, $data) {
            $service->update([
                'name' => $data->name,
                'code' => $data->code ?: $service->code,
                'cost' => $data->cost,
                'description' => $data->description,
                'is_active' => $data->is_active,
            ]);

            return $service;
        });
    }

    /**
     * Delete a service.
     */
    public function deleteService(Service $service): bool
    {
        return DB::transaction(function () use ($service) {
            if ($service->saleItems()->exists()) {
                // If it has sale history, deactivate it rather than breaking historical sales
                $service->update(['is_active' => false]);

                return true;
            }

            return $service->delete();
        });
    }

    /**
     * Search active services for POS or autocomplete.
     */
    public function search(string $query = '', int $limit = 20)
    {
        return Service::query()
            ->where('is_active', true)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
