<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Display the main sales report page.
     */
    public function sales(): View
    {
        return view('reports.sales');
    }

    /**
     * Generate and download / stream the Sales PDF report (full or section-specific).
     */
    public function salesPdf(Request $request, SalesReportService $service)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            $temp = $startDate;
            $startDate = $endDate->copy()->startOfDay();
            $endDate = $temp->copy()->endOfDay();
        }

        $section = $request->query('section', 'full');
        $data = $service->getReportData($startDate, $endDate);

        $prefix = match ($section) {
            'invoices' => 'sales-invoices',
            'brands' => 'sales-brands',
            'items' => 'sales-items',
            'daily' => 'sales-daily-breakdown',
            default => 'sales-report',
        };

        $filename = sprintf(
            '%s-%s-to-%s.pdf',
            $prefix,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $pdf = Pdf::loadView('reports.sales-pdf', [
            'kpis' => $data['kpis'],
            'paymentMethods' => $data['payment_methods'],
            'dailyBreakdown' => $data['daily_breakdown'],
            'brandBreakdown' => $data['brand_breakdown'],
            'itemBreakdown' => $data['item_breakdown'],
            'sales' => $data['sales'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'section' => $section,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isPhpEnabled', false)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);

        if ($request->query('stream') === '1') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    /**
     * Generate and download the Sales XLS / XLSX workbook (full or section-specific).
     */
    public function salesXls(Request $request, SalesReportService $service)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            $temp = $startDate;
            $startDate = $endDate->copy()->startOfDay();
            $endDate = $temp->copy()->endOfDay();
        }

        $section = $request->query('section', 'full');

        return $service->exportXls($startDate, $endDate, $section);
    }
}
