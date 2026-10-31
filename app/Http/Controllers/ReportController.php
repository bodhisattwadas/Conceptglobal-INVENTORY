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
     * Generate and download / stream the Sales PDF report.
     */
    public function salesPdf(Request $request, SalesReportService $service)
    {
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

        $data = $service->getReportData($startDate, $endDate);

        $filename = sprintf(
            'sales-report-%s-to-%s.pdf',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $pdf = Pdf::loadView('reports.sales-pdf', [
            'kpis' => $data['kpis'],
            'paymentMethods' => $data['payment_methods'],
            'dailyBreakdown' => $data['daily_breakdown'],
            'sales' => $data['sales'],
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'portrait');

        if ($request->query('stream') === '1') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }
}
