<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductHsnImportService
{
    /**
     * Generate an XLSX file containing all products in the database.
     */
    public function generateTemplatePath(bool $blankSample = false): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'hsn_tpl_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($tempPath);

        // Header Row
        $writer->addRow(Row::fromValues([
            'Product Code',
            'HSN Code',
            'Product Name',
            'SKU',
        ]));

        if ($blankSample) {
            // Write 2-3 sample guide rows
            $writer->addRow(Row::fromValues([
                '8906036215017',
                '33049900',
                'Sample Strawberry Body Wash 250ml',
                'P.260101.ABCD',
            ]));
            $writer->addRow(Row::fromValues([
                '8906036215024',
                '33051000',
                'Sample Herbal Shampoo 200ml',
                'P.260101.WXYZ',
            ]));
        } else {
            // Stream all products from DB
            Product::query()
                ->select(['id', 'product_code', 'sku', 'name', 'hsn_code'])
                ->orderBy('name')
                ->chunk(500, function ($products) use ($writer) {
                    foreach ($products as $product) {
                        $code = ! empty($product->product_code) ? $product->product_code : ($product->sku ?: '');
                        $writer->addRow(Row::fromValues([
                            (string) $code,
                            (string) ($product->hsn_code ?? ''),
                            (string) ($product->name ?? ''),
                            (string) ($product->sku ?? ''),
                        ]));
                    }
                });
        }

        $writer->close();

        return $tempPath;
    }

    /**
     * Export template as binary file download response.
     */
    public function exportTemplate(bool $blankSample = false): BinaryFileResponse
    {
        $path = $this->generateTemplatePath($blankSample);
        $filename = $blankSample
            ? 'sample_hsn_import_template.xlsx'
            : 'products_hsn_sync_'.date('Y_m_d_His').'.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Import and synchronize HSN codes from an uploaded file (.xlsx or .csv).
     *
     * @return array{
     *     total_rows: int,
     *     updated_count: int,
     *     unchanged_count: int,
     *     failed_count: int,
     *     errors: array<int, string>,
     *     has_more_errors: bool
     * }
     */
    public function importFromPath(string $filePath, ?string $extension = null): array
    {
        $ext = strtolower($extension ?: pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv' || $ext === 'txt') {
            $reader = new CsvReader;
        } else {
            $reader = new XlsxReader;
        }

        try {
            $reader->open($filePath);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to open spreadsheet. Please ensure the file is a valid .xlsx or .csv document.');
        }

        // Preload existing products into memory for O(1) matching
        $products = Product::query()
            ->select(['id', 'product_code', 'sku', 'hsn_code'])
            ->get();

        $byProductCode = [];
        $bySku = [];

        foreach ($products as $p) {
            if (! empty($p->product_code)) {
                $byProductCode[strtolower(trim((string) $p->product_code))] = $p;
            }
            if (! empty($p->sku)) {
                $bySku[strtolower(trim((string) $p->sku))] = $p;
            }
        }

        $totalRows = 0;
        $updatedCount = 0;
        $unchangedCount = 0;
        $failedCount = 0;
        $errors = [];
        $updates = [];

        $codeColIdx = 0;
        $hsnColIdx = 1;
        $skuColIdx = 3;
        $headerDetected = false;
        $rowNum = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowNum++;
                $cells = $row->toArray();

                // Skip fully blank rows
                if (empty(array_filter($cells, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }

                // Check first row for header names
                if (! $headerDetected) {
                    $headerDetected = true;
                    $normalizedHeaders = array_map(fn ($c) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $c)), $cells);

                    $detectedCode = false;
                    $detectedHsn = false;

                    foreach ($normalizedHeaders as $idx => $headerText) {
                        if (in_array($headerText, ['productcode', 'productcodebarcode', 'barcode', 'code'])) {
                            $codeColIdx = $idx;
                            $detectedCode = true;
                        } elseif (in_array($headerText, ['hsncode', 'hsn', 'hsncodenumber', 'hsnnumber'])) {
                            $hsnColIdx = $idx;
                            $detectedHsn = true;
                        } elseif (in_array($headerText, ['sku', 'productsku'])) {
                            $skuColIdx = $idx;
                        }
                    }

                    // If row 1 was indeed a header row, continue to next row
                    if ($detectedCode || $detectedHsn) {
                        continue;
                    }
                }

                $totalRows++;

                $rawCode = isset($cells[$codeColIdx]) ? trim((string) $cells[$codeColIdx]) : '';
                $rawHsn = isset($cells[$hsnColIdx]) ? trim((string) $cells[$hsnColIdx]) : '';
                $rawSku = isset($cells[$skuColIdx]) ? trim((string) $cells[$skuColIdx]) : '';

                if ($rawCode === '' && $rawSku === '') {
                    $failedCount++;
                    $errors[] = "Row {$rowNum}: Missing product code and SKU.";

                    continue;
                }

                // Find matching product
                $product = null;
                if ($rawCode !== '') {
                    $product = $byProductCode[strtolower($rawCode)] ?? ($bySku[strtolower($rawCode)] ?? null);
                }

                if (! $product && $rawSku !== '') {
                    $product = $bySku[strtolower($rawSku)] ?? null;
                }

                if (! $product) {
                    $failedCount++;
                    $identifier = $rawCode ?: $rawSku;
                    $errors[] = "Row {$rowNum}: Product '{$identifier}' not found in database.";

                    continue;
                }

                // Sanitize HSN code
                $cleanHsn = $rawHsn !== '' ? $rawHsn : null;
                $existingHsn = $product->hsn_code !== null ? trim((string) $product->hsn_code) : null;

                if ($cleanHsn !== $existingHsn) {
                    $updates[$product->id] = $cleanHsn;
                    $product->hsn_code = $cleanHsn; // update cached state
                    $updatedCount++;
                } else {
                    $unchangedCount++;
                }
            }

            // Only process the first sheet
            break;
        }

        $reader->close();

        // Perform batch updates in transaction
        if (! empty($updates)) {
            DB::transaction(function () use ($updates) {
                foreach (array_chunk($updates, 250, true) as $chunk) {
                    foreach ($chunk as $productId => $hsnValue) {
                        Product::where('id', $productId)->update(['hsn_code' => $hsnValue]);
                    }
                }
            });
        }

        return [
            'total_rows' => $totalRows,
            'updated_count' => $updatedCount,
            'unchanged_count' => $unchangedCount,
            'failed_count' => $failedCount,
            'errors' => array_slice($errors, 0, 15),
            'has_more_errors' => count($errors) > 15,
        ];
    }
}
