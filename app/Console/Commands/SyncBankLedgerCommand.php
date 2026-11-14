<?php

namespace App\Console\Commands;

use App\Services\BankLedgerService;
use Illuminate\Console\Command;

class SyncBankLedgerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank-ledger:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize and backfill completed UPI, Card, and Bank Transfer sales into the bank register ledger';

    public function __construct(
        protected BankLedgerService $bankLedgerService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Bank Ledger Synchronization...');

        $synced = $this->bankLedgerService->syncHistoricalBankSales();

        $this->info("Successfully synchronized {$synced} completed bank sale transactions into the bank ledger.");

        return self::SUCCESS;
    }
}
