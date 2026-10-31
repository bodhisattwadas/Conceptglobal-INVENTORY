<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportService
{
    /**
     * Get aggregated reporting data for the given date window.
     *
     * @return array{
     *     kpis: array{
     *         net_sales: float,
     *         gross_sales: float,
     *         total_discounts: float,
     *         total_orders: int,
     *         average_order_value: float
     *     },
     *     payment_methods: array<string, array{
     *         key: string,
     *         label: string,
     *         amount: float,
     *         count: int,
     *         percentage: float
     *     }>,
     *     daily_breakdown: Collection
     * }
     */
    public function getReportData(Carbon $startDate, Carbon $endDate): array
    {
        $sales = Sale::query()
            ->with(['customer', 'creator'])
            ->where('status', SaleStatus::COMPLETED)
            ->whereBetween('sale_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderBy('sale_date', 'desc')
            ->get();

        $kpis = $this->calculateKpis($sales);
        $paymentMethods = $this->calculatePaymentMethodBreakdown($sales, $kpis['net_sales']);
        $dailyBreakdown = $this->calculateDailyBreakdown($sales);

        return [
            'kpis' => $kpis,
            'payment_methods' => $paymentMethods,
            'daily_breakdown' => $dailyBreakdown,
            'sales' => $sales,
        ];
    }

    /**
     * Calculate core KPI metrics from sales collection.
     */
    protected function calculateKpis(Collection $sales): array
    {
        $totalOrders = $sales->count();
        $grossSales = (float) $sales->sum(fn ($sale) => (float) $sale->subtotal);
        $netSales = (float) $sales->sum(fn ($sale) => (float) $sale->total);
        $totalDiscounts = (float) $sales->sum(function ($sale) {
            return (float) ($sale->total_discount ?? 0) + (float) ($sale->coupon_discount ?? 0);
        });

        $aov = $totalOrders > 0 ? round($netSales / $totalOrders, 2) : 0.0;

        return [
            'net_sales' => round($netSales, 2),
            'gross_sales' => round($grossSales, 2),
            'total_discounts' => round($totalDiscounts, 2),
            'total_orders' => $totalOrders,
            'average_order_value' => $aov,
        ];
    }

    /**
     * Calculate payment method breakdown with amounts, counts, and revenue shares.
     */
    protected function calculatePaymentMethodBreakdown(Collection $sales, float $totalNetSales): array
    {
        $methods = [
            PaymentMethod::CASH->value => [
                'key' => PaymentMethod::CASH->value,
                'label' => PaymentMethod::CASH->label(),
                'amount' => 0.0,
                'count' => 0,
                'percentage' => 0.0,
            ],
            PaymentMethod::UPI->value => [
                'key' => PaymentMethod::UPI->value,
                'label' => PaymentMethod::UPI->label(),
                'amount' => 0.0,
                'count' => 0,
                'percentage' => 0.0,
            ],
            PaymentMethod::CARD->value => [
                'key' => PaymentMethod::CARD->value,
                'label' => PaymentMethod::CARD->label(),
                'amount' => 0.0,
                'count' => 0,
                'percentage' => 0.0,
            ],
            PaymentMethod::TRANSFER->value => [
                'key' => PaymentMethod::TRANSFER->value,
                'label' => PaymentMethod::TRANSFER->label(),
                'amount' => 0.0,
                'count' => 0,
                'percentage' => 0.0,
            ],
        ];

        foreach ($sales as $sale) {
            $val = $sale->payment_method instanceof PaymentMethod
                ? $sale->payment_method->value
                : (string) $sale->payment_method;

            if (! isset($methods[$val])) {
                $methods[$val] = [
                    'key' => $val,
                    'label' => ucfirst($val),
                    'amount' => 0.0,
                    'count' => 0,
                    'percentage' => 0.0,
                ];
            }

            $methods[$val]['amount'] += (float) $sale->total;
            $methods[$val]['count'] += 1;
        }

        foreach ($methods as $key => $data) {
            $methods[$key]['amount'] = round($data['amount'], 2);
            $methods[$key]['percentage'] = $totalNetSales > 0
                ? round(($data['amount'] / $totalNetSales) * 100, 1)
                : 0.0;
        }

        return $methods;
    }

    /**
     * Calculate day-by-day sales & payment distribution.
     */
    protected function calculateDailyBreakdown(Collection $sales): Collection
    {
        return $sales
            ->groupBy(fn ($sale) => $sale->sale_date ? $sale->sale_date->format('Y-m-d') : 'Unknown')
            ->map(function (Collection $daySales, string $date) {
                $gross = (float) $daySales->sum(fn ($s) => (float) $s->subtotal);
                $discounts = (float) $daySales->sum(fn ($s) => (float) ($s->total_discount ?? 0) + (float) ($s->coupon_discount ?? 0));
                $net = (float) $daySales->sum(fn ($s) => (float) $s->total);

                $cash = (float) $daySales->where(function ($s) {
                    $val = $s->payment_method instanceof PaymentMethod ? $s->payment_method->value : $s->payment_method;

                    return $val === PaymentMethod::CASH->value;
                })->sum('total');

                $upi = (float) $daySales->where(function ($s) {
                    $val = $s->payment_method instanceof PaymentMethod ? $s->payment_method->value : $s->payment_method;

                    return $val === PaymentMethod::UPI->value;
                })->sum('total');

                $card = (float) $daySales->where(function ($s) {
                    $val = $s->payment_method instanceof PaymentMethod ? $s->payment_method->value : $s->payment_method;

                    return $val === PaymentMethod::CARD->value;
                })->sum('total');

                $transfer = (float) $daySales->where(function ($s) {
                    $val = $s->payment_method instanceof PaymentMethod ? $s->payment_method->value : $s->payment_method;

                    return $val === PaymentMethod::TRANSFER->value;
                })->sum('total');

                $formattedDate = $date !== 'Unknown'
                    ? Carbon::parse($date)->format('d M Y')
                    : 'Unknown';

                return [
                    'date' => $date,
                    'formatted_date' => $formattedDate,
                    'orders_count' => $daySales->count(),
                    'gross_sales' => round($gross, 2),
                    'total_discounts' => round($discounts, 2),
                    'net_sales' => round($net, 2),
                    'cash' => round($cash, 2),
                    'upi' => round($upi, 2),
                    'card' => round($card, 2),
                    'transfer' => round($transfer, 2),
                ];
            })
            ->values();
    }

    /**
     * Generate streamed CSV file of all sales in the date range.
     */
    public function exportCsv(Carbon $startDate, Carbon $endDate): StreamedResponse
    {
        $filename = sprintf(
            'sales-report-%s-to-%s.csv',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $sales = Sale::query()
            ->with(['customer', 'creator'])
            ->where('status', SaleStatus::COMPLETED)
            ->whereBetween('sale_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderBy('sale_date', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($sales) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel compatibility with UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($handle, [
                'Invoice #',
                'Date & Time',
                'Customer Name',
                'Customer Phone',
                'Payment Method',
                'Gross Amount (INR)',
                'Item Discount (INR)',
                'Coupon Discount (INR)',
                'Net Total (INR)',
                'Cashier',
                'Status',
            ]);

            foreach ($sales as $sale) {
                $method = $sale->payment_method instanceof PaymentMethod
                    ? $sale->payment_method->label()
                    : ucfirst((string) $sale->payment_method);

                fputcsv($handle, [
                    $sale->invoice_number,
                    $sale->sale_date ? $sale->sale_date->format('Y-m-d H:i') : '',
                    $sale->customer?->name ?? 'Walk-in Customer',
                    $sale->customer?->phone ?? '',
                    $method,
                    number_format((float) $sale->subtotal, 2, '.', ''),
                    number_format((float) $sale->total_discount, 2, '.', ''),
                    number_format((float) $sale->coupon_discount, 2, '.', ''),
                    number_format((float) $sale->total, 2, '.', ''),
                    $sale->creator?->name ?? 'N/A',
                    $sale->status instanceof SaleStatus ? $sale->status->label() : ucfirst((string) $sale->status),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
