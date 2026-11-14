<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryAdjustmentReportService
{
    /**
     * Compute summary KPIs and aggregated metrics for inventory adjustments over a period.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getKpiSummary(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $query = $this->buildBaseQuery($startDate, $endDate, $filters);

        $adjustments = $query->get();

        $damageAdjustments = $adjustments->where('type', 'quantity_decrease');
        $surplusAdjustments = $adjustments->where('type', 'quantity_increase');
        $reallocationAdjustments = $adjustments->where('type', 'reallocation');
        $transferAdjustments = $adjustments->where('type', 'location_transfer');

        $damageUnits = (int) $damageAdjustments->sum('quantity');
        $damageAmount = (float) $damageAdjustments->sum(fn ($a) => (int) $a->quantity * (float) $a->unit_cost);

        $surplusUnits = (int) $surplusAdjustments->sum('quantity');
        $surplusAmount = (float) $surplusAdjustments->sum(fn ($a) => (int) $a->quantity * (float) $a->unit_cost);

        $reallocationUnits = (int) $reallocationAdjustments->sum('quantity');
        $reallocationAmount = (float) $reallocationAdjustments->sum(fn ($a) => (int) $a->quantity * (float) $a->unit_cost);

        $transferUnits = (int) $transferAdjustments->sum('quantity');

        // Net impact: Surplus added minus Damage written off
        $netAmount = round($surplusAmount - $damageAmount, 2);
        $netUnits = $surplusUnits - $damageUnits;

        // Gross total posted amount (absolute value of damage + surplus adjustments posted)
        $grossAmount = round($damageAmount + $surplusAmount, 2);
        $totalPostedAdjustments = $adjustments->count();

        // Reason breakdown
        $reasonBreakdown = $adjustments->groupBy('reason')->map(function (Collection $group, string $reason) {
            $totalQty = (int) $group->sum('quantity');
            $totalVal = (float) $group->sum(fn ($a) => (int) $a->quantity * (float) $a->unit_cost);

            return [
                'reason' => $reason,
                'label' => $this->formatReasonLabel($reason),
                'count' => $group->count(),
                'units' => $totalQty,
                'amount' => round($totalVal, 2),
            ];
        })->sortByDesc('amount')->values();

        // Location breakdown
        $storeAdjustments = $adjustments->filter(fn ($a) => ($a->source_location?->value ?? ($a->target_location?->value ?? 'shop')) === 'shop');
        $parlourAdjustments = $adjustments->filter(fn ($a) => ($a->source_location?->value ?? ($a->target_location?->value ?? 'shop')) === 'parlour');

        return [
            'total_count' => $totalPostedAdjustments,
            'damage_count' => $damageAdjustments->count(),
            'damage_units' => $damageUnits,
            'damage_amount' => round($damageAmount, 2),
            'surplus_count' => $surplusAdjustments->count(),
            'surplus_units' => $surplusUnits,
            'surplus_amount' => round($surplusAmount, 2),
            'net_amount' => $netAmount,
            'net_units' => $netUnits,
            'gross_amount' => $grossAmount,
            'reallocation_count' => $reallocationAdjustments->count(),
            'reallocation_units' => $reallocationUnits,
            'reallocation_amount' => round($reallocationAmount, 2),
            'transfer_count' => $transferAdjustments->count(),
            'transfer_units' => $transferUnits,
            'reason_breakdown' => $reasonBreakdown,
            'store_count' => $storeAdjustments->count(),
            'store_amount' => round($storeAdjustments->sum(fn ($a) => (int) $a->quantity * (float) $a->unit_cost), 2),
            'parlour_count' => $parlourAdjustments->count(),
            'parlour_amount' => round($parlourAdjustments->sum(fn ($a) => (int) $a->quantity * (float) $a->unit_cost), 2),
        ];
    }

    /**
     * Build the query builder with relationships, date boundaries, and optional filters applied.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAdjustmentsQuery(Carbon $startDate, Carbon $endDate, array $filters = []): Builder
    {
        $query = $this->buildBaseQuery($startDate, $endDate, $filters);

        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortField === 'total_amount') {
            $query->orderByRaw('(quantity * unit_cost) '.$sortDirection);
        } elseif (in_array($sortField, ['adjustment_number', 'type', 'quantity', 'unit_cost', 'created_at'], true)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        return $query;
    }

    /**
     * Internal base query builder.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function buildBaseQuery(Carbon $startDate, Carbon $endDate, array $filters = []): Builder
    {
        $query = InventoryAdjustment::query()
            ->with([
                'sourceProduct.unit',
                'sourceProduct.company',
                'targetProduct',
                'sourcePurchaseItem.purchase.supplier',
                'creator',
            ])
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Type filter:
        // 'all' = default (Damage + Surplus)
        // 'quantity_decrease' = Write-off / Damage only
        // 'quantity_increase' = Surplus / Found only
        // 'all_types' = Everything including reallocation & transfer
        $type = $filters['type'] ?? 'all';
        if ($type === 'all') {
            $query->whereIn('type', ['quantity_decrease', 'quantity_increase']);
        } elseif (in_array($type, ['quantity_decrease', 'quantity_increase', 'reallocation', 'location_transfer'], true)) {
            $query->where('type', $type);
        }

        // Location filter: 'all', 'shop', 'parlour'
        $location = $filters['location'] ?? 'all';
        if (in_array($location, ['shop', 'parlour'], true)) {
            $query->where(function (Builder $q) use ($location) {
                $q->where('source_location', $location)
                    ->orWhere('target_location', $location);
            });
        }

        // Reason filter
        if (! empty($filters['reason']) && $filters['reason'] !== 'all') {
            $query->where('reason', $filters['reason']);
        }

        // Search string (product name, sku, product_code, adjustment_number, batch_number)
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('adjustment_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('sourceProduct', function (Builder $prod) use ($search) {
                        $prod->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('product_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('targetProduct', function (Builder $prod) use ($search) {
                        $prod->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('product_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('sourcePurchaseItem', function (Builder $item) use ($search) {
                        $item->where('batch_number', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * Stream CSV export of adjustments.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(Carbon $startDate, Carbon $endDate, array $filters = []): StreamedResponse
    {
        $kpis = $this->getKpiSummary($startDate, $endDate, $filters);
        $adjustments = $this->getAdjustmentsQuery($startDate, $endDate, $filters)->get();

        $filename = sprintf(
            'inventory-adjustments-%s-to-%s.csv',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($kpis, $adjustments, $startDate, $endDate) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // Title & Period Summary
            fputcsv($out, ['INVENTORY STOCK ADJUSTMENTS REPORT']);
            fputcsv($out, ['Period:', $startDate->format('d/m/Y').' to '.$endDate->format('d/m/Y')]);
            fputcsv($out, ['Generated At:', now()->format('d/m/Y h:i A')]);
            fputcsv($out, []);

            // Summary KPIs
            fputcsv($out, ['SUMMARY KPI', 'COUNT', 'UNITS', 'AMOUNT (INR)']);
            fputcsv($out, ['Write-Off / Damage (Loss)', $kpis['damage_count'], $kpis['damage_units'], number_format($kpis['damage_amount'], 2, '.', '')]);
            fputcsv($out, ['Surplus / Found (Gain)', $kpis['surplus_count'], $kpis['surplus_units'], number_format($kpis['surplus_amount'], 2, '.', '')]);
            fputcsv($out, ['Net Adjustment Impact', $kpis['total_count'], $kpis['net_units'], number_format($kpis['net_amount'], 2, '.', '')]);
            fputcsv($out, ['Gross Adjustments Posted', $kpis['total_count'], $kpis['damage_units'] + $kpis['surplus_units'], number_format($kpis['gross_amount'], 2, '.', '')]);
            fputcsv($out, []);

            // Table Header
            fputcsv($out, [
                'Adjustment No',
                'Date & Time',
                'Type',
                'Product Name',
                'Product Code',
                'SKU',
                'Location',
                'Batch No',
                'Reason',
                'Adjusted Quantity',
                'Unit Cost (INR)',
                'Total Amount (INR)',
                'Posted By',
                'Notes',
            ]);

            // Table Rows
            foreach ($adjustments as $adj) {
                $typeLabel = match ($adj->type) {
                    'quantity_decrease' => 'Write-Off / Damage',
                    'quantity_increase' => 'Surplus / Found',
                    'reallocation' => 'Reallocation',
                    'location_transfer' => 'Location Movement',
                    default => ucfirst((string) $adj->type),
                };

                $locationLabel = match ($adj->source_location?->value ?? $adj->target_location?->value) {
                    'parlour' => 'Parlour (Back)',
                    default => 'Store / Shop',
                };

                $totalAmount = (int) $adj->quantity * (float) $adj->unit_cost;
                $signedQty = $adj->type === 'quantity_decrease' ? -((int) $adj->quantity) : (int) $adj->quantity;

                fputcsv($out, [
                    $adj->adjustment_number,
                    $adj->created_at?->format('d/m/Y h:i A') ?: '-',
                    $typeLabel,
                    $adj->sourceProduct?->name ?: 'N/A',
                    $adj->sourceProduct?->product_code ?: '-',
                    $adj->sourceProduct?->sku ?: '-',
                    $locationLabel,
                    $adj->sourcePurchaseItem?->batch_number ?: '-',
                    $this->formatReasonLabel($adj->reason),
                    $signedQty,
                    number_format((float) $adj->unit_cost, 2, '.', ''),
                    number_format($totalAmount, 2, '.', ''),
                    $adj->creator?->name ?: 'System',
                    $adj->notes ?: '-',
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Generate PDF view instance.
     *
     * @param  array<string, mixed>  $filters
     */
    public function generatePdf(Carbon $startDate, Carbon $endDate, array $filters = []): \Barryvdh\DomPDF\PDF
    {
        $kpis = $this->getKpiSummary($startDate, $endDate, $filters);
        $adjustments = $this->getAdjustmentsQuery($startDate, $endDate, $filters)->get();

        $storeName = Setting::get('store_name', config('app.name'));
        $storeAddress = Setting::get('store_address', '-');
        $storePhone = Setting::get('store_phone', '-');
        $storeEmail = Setting::get('store_email', '-');

        return Pdf::loadView('reports.adjustments-pdf', [
            'kpis' => $kpis,
            'adjustments' => $adjustments,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'storeName' => $storeName,
            'storeAddress' => $storeAddress,
            'storePhone' => $storePhone,
            'storeEmail' => $storeEmail,
            'filters' => $filters,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isPhpEnabled', false)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Convert reason slug into human-readable label.
     */
    public function formatReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'damaged' => 'Damaged Goods',
            'expired' => 'Expired Stock',
            'theft_loss' => 'Theft / Shrinkage',
            'count_correction' => 'Physical Count Discrepancy',
            'found_correction' => 'Found Goods / Count Surplus',
            'supplier_bonus' => 'Supplier Free Samples / Bonus',
            'misallocation_split' => 'PO Misallocation / Split',
            'transfer_to_shop' => 'Transfer to Store / Shop',
            'transfer_to_parlour' => 'Transfer to Parlour',
            'rebalance' => 'Location Rebalance',
            'other' => 'Other Adjustment',
            null, '' => 'General Adjustment',
            default => ucwords(str_replace('_', ' ', $reason)),
        };
    }
}
