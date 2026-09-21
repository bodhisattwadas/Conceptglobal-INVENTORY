<?php

namespace App\Services;

use Exception;
use App\Models\Supplier;
use App\DTOs\SupplierData;
use Illuminate\Support\Facades\DB;
use App\Exceptions\SupplierException;
use Illuminate\Support\Facades\Cache;

class SupplierService
{
    /**
     * Create a new supplier record.
     */
    public function createSupplier(SupplierData $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            try {
                $supplier = Supplier::create([
                    ...$this->attributesFromData($data),
                ]);
                $supplier->companies()->sync($data->company_ids);

                Cache::forget('suppliers_list_all');

                return $supplier;

            } catch (Exception $e) {
                throw SupplierException::creationFailed($e->getMessage(), [
                    'data' => (array) $data,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }

    /**
     * Update an existing supplier record.
     */
    public function updateSupplier(Supplier $supplier, SupplierData $data): Supplier
    {
        if (! $supplier->exists) {
            return $this->createSupplier($data);
        }

        return DB::transaction(function () use ($supplier, $data) {
            try {
                $supplier->update([
                    ...$this->attributesFromData($data, $supplier),
                ]);
                $supplier->companies()->sync($data->company_ids);

                Cache::forget('suppliers_list_all');

                return $supplier->refresh();

            } catch (Exception $e) {
                throw SupplierException::updateFailed($e->getMessage(), [
                    'id'   => $supplier->id,
                    'data' => (array) $data
                ]);
            }
        });
    }

    /**
     * Delete a supplier record.
     */
    public function deleteSupplier(Supplier $supplier): void
    {
        DB::transaction(function () use ($supplier) {
            try {
                if ($supplier->purchases()->exists()) {
                    throw new Exception('Cannot delete supplier because there are purchases associated with this supplier.');
                }

                $supplier->delete();

                Cache::forget('suppliers_list_all');

            } catch (Exception $e) {
                throw SupplierException::deletionFailed($e->getMessage(), ['id' => $supplier->id]);
            }
        });
    }

    private function attributesFromData(SupplierData $data, ?Supplier $supplier = null): array
    {
        $attributes = [
            'name' => $data->name,
            'legal_name' => $data->legal_name,
            'trade_name' => $data->trade_name,
            'supplier_type' => $data->supplier_type,
            'registration_number' => $data->registration_number,
            'tax_id' => $data->tax_id,
            'website' => $data->website,
            'industry' => $data->industry,
            'contact_person' => $data->contact_person,
            'email' => $data->email,
            'accounts_email' => $data->accounts_email,
            'purchase_email' => $data->purchase_email,
            'phone' => $data->phone,
            'alternate_phone' => $data->alternate_phone,
            'address' => $data->address,
            'address_line_1' => $data->address_line_1,
            'address_line_2' => $data->address_line_2,
            'city' => $data->city,
            'state' => $data->state,
            'postal_code' => $data->postal_code,
            'country' => $data->country,
            'bank_name' => $data->bank_name,
            'bank_branch' => $data->bank_branch,
            'account_name' => $data->account_name,
            'account_type' => $data->account_type,
            'ifsc_code' => $data->ifsc_code,
            'swift_bic' => $data->swift_bic,
            'beneficiary_name' => $data->beneficiary_name,
            'bank_country' => $data->bank_country,
            'blank_cheque_path' => $data->blank_cheque_path,
            'gst_document_path' => $data->gst_document_path,
            'status' => $data->status,
            'notes' => $data->notes,
        ];

        if ($data->account_number) {
            $workingSupplier = $supplier ?? new Supplier();
            $workingSupplier->account_number = $data->account_number;
            $attributes['account_number_encrypted'] = $workingSupplier->account_number_encrypted;
            $attributes['account_number_last4'] = $workingSupplier->account_number_last4;
        }

        return $attributes;
    }
}
