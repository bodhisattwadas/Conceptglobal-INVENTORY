<?php

namespace App\Console\Commands;

use App\Services\CashLedgerService;
use Illuminate\Console\Command;

class SyncCashLedgerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cash-ledger:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize and backfill completed cash sales into the cash register ledger';

    public function __construct(
        protected CashLedgerService $cashLedgerService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Cash Ledger Synchronization...');

        $synced = $this->cashLedgerService->syncHistoricalCashSales();

        $this->info("Successfully synchronized {$synced} cash sale transactions into the cash ledger.");

        return self::SUCCESS;
    }
}
