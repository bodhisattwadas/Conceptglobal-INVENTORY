<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Services\DashboardStatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AuditDashboardKpiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard:audit-kpi {period=yesterday : Period key (yesterday, today, this_week, this_month, last_month, all_time) or specific date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and log detailed parameters for Dashboard KPI calculations';

    /**
     * Execute the console command.
     */
    public function handle(DashboardStatsService $service): int
    {
        $periodInput = strtolower(trim((string) $this->argument('period')));
        $now = Carbon::now();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodInput)) {
            $startDate = Carbon::parse($periodInput)->startOfDay();
            $endDate = Carbon::parse($periodInput)->endOfDay();
            $periodKey = $periodInput;
        } else {
            $periodKey = $periodInput;
            [$startDate, $endDate] = match ($periodInput) {
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
                'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
                'all_time' => [
                    Carbon::parse(Sale::min('sale_date') ?? now()->subYears(5))->startOfDay(),
                    $now->copy()->endOfDay(),
                ],
                default => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            };
        }

        $this->info("Auditing KPI for period: {$periodKey} ({$startDate->toDateTimeString()} to {$endDate->toDateTimeString()})...");
        $report = $service->logKpiBreakdown($startDate, $endDate, $periodKey);
        foreach (explode(PHP_EOL, $report) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
