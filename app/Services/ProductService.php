<?php

namespace App\Services;

use App\DTOs\ProductData;
use App\Exceptions\ProductException;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Create a new product.
     */
    public function createProduct(ProductData $data): Product
    {
        return DB::transaction(function () use ($data) {
            try {
                $sku = $data->sku ?? $this->generateUniqueSku();

                $product = Product::create([
                    'category_id' => $data->category_id,
                    'unit_id' => $data->unit_id,
                    'company_id' => $data->company_id,
                    'sku' => $sku,
                    'product_code' => $data->product_code,
                    'name' => $data->name,
                    'mrp' => $data->mrp,
                    'purchase_price' => $data->mrp,
                    'selling_price' => $data->mrp,
                    'quantity' => 0,
                    'min_stock' => $data->min_stock,
                    'is_active' => $data->is_active,
                    'description' => $data->description,
                    'notes' => $data->notes,
                    'image_path' => $data->image_path,
                ]);

                $product->priceHistories()->create([
                    'changed_by' => Auth::id(),
                    'source' => 'product',
                    'reference' => 'Initial product price',
                    'new_mrp' => $product->mrp,
                    'notes' => 'Product created.',
                ]);

                return $product;

            } catch (Exception $e) {
                throw ProductException::creationFailed($e->getMessage(), [
                    'data' => (array) $data,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });
    }

    /**
     * Update an existing product.
     */
    public function updateProduct(Product $product, ProductData $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            try {
                $oldImagePath = $product->image_path;
                $oldPrices = [
                    'mrp' => (int) $product->mrp,
                ];

                $product->update([
                    'category_id' => $data->category_id,
                    'unit_id' => $data->unit_id,
                    'company_id' => $data->company_id,
                    'sku' => $data->sku ?? $product->sku,
                    'product_code' => $data->product_code,
                    'name' => $data->name,
                    'mrp' => $data->mrp,
                    'purchase_price' => $data->mrp,
                    'selling_price' => $data->mrp,
                    'min_stock' => $data->min_stock,
                    'is_active' => $data->is_active,
                    'description' => $data->description,
                    'notes' => $data->notes,
                    'image_path' => $data->image_path ?? $product->image_path,
                ]);

                if ($data->image_path && $oldImagePath && $oldImagePath !== $data->image_path) {
                    Storage::disk('public')->delete($oldImagePath);
                }

                if (
                    $oldPrices['mrp'] !== (int) $product->mrp
                ) {
                    $product->priceHistories()->create([
                        'changed_by' => Auth::id(),
                        'source' => 'product',
                        'reference' => 'Product edit',
                        'old_mrp' => $oldPrices['mrp'],
                        'new_mrp' => $product->mrp,
                    ]);
                }

                return $product->refresh();

            } catch (Exception $e) {
                throw ProductException::updateFailed($e->getMessage(), [
                    'id' => $product->id,
                    'data' => (array) $data,
                ]);
            }
        });
    }

    /**
     * Delete a product.
     */
    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            try {
                if ($product->purchaseItems()->exists() || $product->saleItems()->exists()) {
                    throw new Exception('Cannot delete product because it is associated with purchase or sale records.');
                }

                $imagePath = $product->image_path;

                $product->delete();

                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }

            } catch (Exception $e) {
                throw ProductException::deletionFailed($e->getMessage(), ['id' => $product->id]);
            }
        });
    }

    /**
     * Generate a unique SKU in format P.YYMMDD.XXXX.
     */
    private function generateUniqueSku(): string
    {
        $prefix = 'P.'.date('ymd').'.';

        do {
            $sku = $prefix.strtoupper(Str::random(4));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }
}
