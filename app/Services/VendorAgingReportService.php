<?php

namespace App\Services;

use App\Enums\VendorInvoiceStatus;
use App\Models\Setting;
use App\Models\VendorInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorAgingReportService
{
    /**
     * Retrieve full report dataset including summary KPIs, vendor breakdown, and detailed invoices.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getReportData(array $filters = []): array
    {
        // 1. Fetch eligible open invoices (unpaid and partially paid with net due > 0)
        // Strictly exclude invoices marked 'paid' or where remaining due <= 0.00.
        $query = VendorInvoice::query()
            ->with(['supplier', 'purchase', 'company'])
            ->where('status', '!=', VendorInvoiceStatus::PAID->value)
            ->whereRaw('(amount - paid_amount) > 0');

        // Supplier / Vendor filter
        if (! empty($filters['supplier_id']) && $filters['supplier_id'] !== 'all') {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        // Search filter (invoice number, po reference, vendor details)
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('po_reference', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $rawInvoices = $query->get();

        // 2. Map and compute aging calculations using PHP Carbon for portability across SQLite and MySQL
        $today = Carbon::today()->startOfDay();

        $mappedInvoices = $rawInvoices->map(function (VendorInvoice $invoice) use ($today) {
            // Date hierarchy: invoice_date -> order_received_date -> created_at
            $effectiveDateRaw = $invoice->invoice_date ?? $invoice->order_received_date ?? $invoice->created_at;
            $effectiveDate = Carbon::parse($effectiveDateRaw)->startOfDay();
            $ageInDays = max(0, (int) $effectiveDate->diffInDays($today, false));

            $dueAmount = max(0, (float) $invoice->amount - (float) $invoice->paid_amount);

            $bucket = match (true) {
                $ageInDays <= 30 => '0-30',
                $ageInDays <= 60 => '31-60',
                $ageInDays <= 90 => '61-90',
                default => '90+',
            };

            $bucketLabel = match ($bucket) {
                '0-30' => '0–30 Days (Current)',
                '31-60' => '31–60 Days',
                '61-90' => '61–90 Days',
                default => '90+ Days',
            };

            // Dynamically assign computed properties
            $invoice->effective_date = $effectiveDate;
            $invoice->age_in_days = $ageInDays;
            $invoice->due_amount = $dueAmount;
            $invoice->aging_bucket = $bucket;
            $invoice->aging_bucket_label = $bucketLabel;

            return $invoice;
        });

        // Strictly ensure due_amount > 0 and status is not paid
        $mappedInvoices = $mappedInvoices->filter(function (VendorInvoice $invoice) {
            $isPaidStatus = $invoice->status instanceof VendorInvoiceStatus
                ? $invoice->status === VendorInvoiceStatus::PAID
                : (string) $invoice->status === VendorInvoiceStatus::PAID->value;

            return ! $isPaidStatus && $invoice->due_amount > 0.00;
        });

        // 3. Bucket filter (if active)
        $filteredInvoices = $mappedInvoices;
        if (! empty($filters['bucket']) && $filters['bucket'] !== 'all') {
            $filteredInvoices = $filteredInvoices->filter(fn (VendorInvoice $inv) => $inv->aging_bucket === $filters['bucket']);
        }

        // 4. Compute Summary KPIs from the filtered dataset
        $totalDue = round((float) $filteredInvoices->sum('due_amount'), 2);
        $bucket0To30Due = round((float) $filteredInvoices->where('aging_bucket', '0-30')->sum('due_amount'), 2);
        $bucket31To60Due = round((float) $filteredInvoices->where('aging_bucket', '31-60')->sum('due_amount'), 2);
        $bucket61To90Due = round((float) $filteredInvoices->where('aging_bucket', '61-90')->sum('due_amount'), 2);
        $bucket90PlusDue = round((float) $filteredInvoices->where('aging_bucket', '90+')->sum('due_amount'), 2);
        $invoicesCount = $filteredInvoices->count();
        $vendorsCount = $filteredInvoices->pluck('supplier_id')->filter()->unique()->count();

        $kpis = [
            'total_due' => $totalDue,
            'bucket_0_30_due' => $bucket0To30Due,
            'bucket_31_60_due' => $bucket31To60Due,
            'bucket_61_90_due' => $bucket61To90Due,
            'bucket_90_plus_due' => $bucket90PlusDue,
            'invoices_count' => $invoicesCount,
            'vendors_count' => $vendorsCount,
        ];

        // 5. Compute Vendor Breakdown grouped by supplier
        $vendorBreakdown = $filteredInvoices
            ->groupBy('supplier_id')
            ->map(function (Collection $group, $supplierId) {
                /** @var VendorInvoice $first */
                $first = $group->first();
                $supplier = $first->supplier;

                $b0 = round((float) $group->where('aging_bucket', '0-30')->sum('due_amount'), 2);
                $b31 = round((float) $group->where('aging_bucket', '31-60')->sum('due_amount'), 2);
                $b61 = round((float) $group->where('aging_bucket', '61-90')->sum('due_amount'), 2);
                $b90 = round((float) $group->where('aging_bucket', '90+')->sum('due_amount'), 2);
                $supplierTotal = round((float) $group->sum('due_amount'), 2);

                return [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplier?->name ?: 'Unknown Vendor',
                    'contact_person' => $supplier?->contact_person ?: '-',
                    'phone' => $supplier?->phone ?: '-',
                    'email' => $supplier?->email ?: '-',
                    'invoice_count' => $group->count(),
                    'bucket_0_30' => $b0,
                    'bucket_31_60' => $b31,
                    'bucket_61_90' => $b61,
                    'bucket_90_plus' => $b90,
                    'total_due' => $supplierTotal,
                ];
            })
            ->sortByDesc('total_due')
            ->values();

        // 6. Sort Detailed Invoices
        $sortField = $filters['sort_field'] ?? 'age_in_days';
        $sortDirection = strtolower($filters['sort_direction'] ?? 'desc');
        $descending = $sortDirection === 'desc';

        $sortedInvoices = $filteredInvoices->sortBy(function (VendorInvoice $invoice) use ($sortField) {
            return match ($sortField) {
                'invoice_number' => strtolower((string) $invoice->invoice_number),
                'supplier_name' => strtolower((string) ($invoice->supplier?->name ?? '')),
                'effective_date' => $invoice->effective_date->timestamp,
                'amount' => (float) $invoice->amount,
                'due_amount' => (float) $invoice->due_amount,
                default => (int) $invoice->age_in_days,
            };
        }, SORT_REGULAR, $descending)->values();

        return [
            'kpis' => $kpis,
            'vendor_breakdown' => $vendorBreakdown,
            'invoices' => $sortedInvoices,
            'total_invoices_count' => $sortedInvoices->count(),
        ];
    }

    /**
     * Generate A4 landscape PDF report instance.
     *
     * @param  array<string, mixed>  $filters
     */
    public function generatePdf(array $filters = []): \Barryvdh\DomPDF\PDF
    {
        $data = $this->getReportData($filters);

        $storeName = Setting::get('store_name', config('app.name'));
        $storeAddress = Setting::get('store_address', '-');
        $storePhone = Setting::get('store_phone', '-');
        $storeEmail = Setting::get('store_email', '-');

        return Pdf::loadView('reports.vendor-aging-pdf', [
            'kpis' => $data['kpis'],
            'vendorBreakdown' => $data['vendor_breakdown'],
            'invoices' => $data['invoices'],
            'filters' => $filters,
            'storeName' => $storeName,
            'storeAddress' => $storeAddress,
            'storePhone' => $storePhone,
            'storeEmail' => $storeEmail,
            'generatedAt' => now()->format('d/m/Y h:i A'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isPhpEnabled', false)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Stream CSV export with UTF-8 BOM, KPI summary, vendor breakdown, and invoice rows.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        $data = $this->getReportData($filters);
        $kpis = $data['kpis'];
        $vendorBreakdown = $data['vendor_breakdown'];
        $invoices = $data['invoices'];

        $filename = 'vendor-aging-report-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($kpis, $vendorBreakdown, $invoices, $filters) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // Title & Metadata
            fputcsv($out, ['ACCOUNTS PAYABLE VENDOR AGING REPORT']);
            fputcsv($out, ['Generated At:', now()->format('d/m/Y h:i A')]);
            if (! empty($filters['supplier_name'])) {
                fputcsv($out, ['Vendor Filter:', $filters['supplier_name']]);
            }
            if (! empty($filters['bucket']) && $filters['bucket'] !== 'all') {
                fputcsv($out, ['Aging Bucket Filter:', $filters['bucket']]);
            }
            if (! empty($filters['search'])) {
                fputcsv($out, ['Search Keyword:', $filters['search']]);
            }
            fputcsv($out, []);

            // Summary KPIs
            fputcsv($out, ['SUMMARY KPIS']);
            fputcsv($out, ['KPI Metric', 'Value']);
            fputcsv($out, ['Total Outstanding Due', number_format($kpis['total_due'], 2, '.', '')]);
            fputcsv($out, ['0–30 Days (Current)', number_format($kpis['bucket_0_30_due'], 2, '.', '')]);
            fputcsv($out, ['31–60 Days', number_format($kpis['bucket_31_60_due'], 2, '.', '')]);
            fputcsv($out, ['61–90 Days', number_format($kpis['bucket_61_90_due'], 2, '.', '')]);
            fputcsv($out, ['90+ Days', number_format($kpis['bucket_90_plus_due'], 2, '.', '')]);
            fputcsv($out, ['Unpaid Invoices Count', $kpis['invoices_count']]);
            fputcsv($out, ['Vendors Count', $kpis['vendors_count']]);
            fputcsv($out, []);

            // Vendor Breakdown
            fputcsv($out, ['VENDOR BREAKDOWN']);
            fputcsv($out, [
                'Vendor / Supplier',
                'Contact Person',
                'Phone',
                'Invoice Count',
                '0–30 Days (INR)',
                '31–60 Days (INR)',
                '61–90 Days (INR)',
                '90+ Days (INR)',
                'Total Due (INR)',
            ]);

            foreach ($vendorBreakdown as $row) {
                fputcsv($out, [
                    $row['supplier_name'],
                    $row['contact_person'],
                    $row['phone'],
                    $row['invoice_count'],
                    number_format($row['bucket_0_30'], 2, '.', ''),
                    number_format($row['bucket_31_60'], 2, '.', ''),
                    number_format($row['bucket_61_90'], 2, '.', ''),
                    number_format($row['bucket_90_plus'], 2, '.', ''),
                    number_format($row['total_due'], 2, '.', ''),
                ]);
            }
            fputcsv($out, []);

            // Detailed Invoice Listing
            fputcsv($out, ['DETAILED INVOICE LISTING']);
            fputcsv($out, [
                'Invoice Number',
                'PO Reference',
                'Vendor / Supplier',
                'Invoice Date',
                'Order Received Date',
                'Effective Date',
                'Age (Days)',
                'Aging Bucket',
                'Status',
                'Total Amount (INR)',
                'Paid Amount (INR)',
                'Due Balance (INR)',
            ]);

            foreach ($invoices as $inv) {
                $statusLabel = $inv->status instanceof VendorInvoiceStatus
                    ? $inv->status->label()
                    : ($inv->status ? ucfirst((string) $inv->status) : 'Unpaid');

                fputcsv($out, [
                    $inv->invoice_number ?: '-',
                    $inv->po_reference ?: '-',
                    $inv->supplier?->name ?: 'Unknown Vendor',
                    $inv->invoice_date?->format('d/m/Y') ?: '-',
                    $inv->order_received_date?->format('d/m/Y') ?: '-',
                    $inv->effective_date?->format('d/m/Y') ?: '-',
                    $inv->age_in_days,
                    $inv->aging_bucket_label,
                    $statusLabel,
                    number_format((float) $inv->amount, 2, '.', ''),
                    number_format((float) $inv->paid_amount, 2, '.', ''),
                    number_format((float) $inv->due_amount, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }
}
