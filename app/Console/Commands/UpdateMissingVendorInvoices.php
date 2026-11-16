<?php

namespace App\Console\Commands;

use App\Models\VendorInvoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateMissingVendorInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vendor-invoices:update-missing {--limit= : Limit the number of invoices to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign supplier invoice numbers and dates to existing blank vendor invoices interactively.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Searching for vendor invoices with missing invoice numbers...');

        $query = VendorInvoice::query()
            ->with(['supplier', 'purchase'])
            ->where(function ($q) {
                $q->whereNull('invoice_number')
                    ->orWhere('invoice_number', '')
                    ->orWhereRaw("TRIM(invoice_number) = ''");
            })
            ->orderBy('id', 'asc');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $blankInvoices = $query->get();

        if ($blankInvoices->isEmpty()) {
            $this->info('No blank vendor invoices found.');

            return self::SUCCESS;
        }

        $total = $blankInvoices->count();
        $this->info("Found {$total} vendor invoice(s) with missing invoice number.");

        $updated = 0;
        $skipped = 0;
        $cancelled = false;

        foreach ($blankInvoices as $index => $invoice) {
            $currentNum = $index + 1;
            $this->line('');
            $this->line(str_repeat('-', 60));
            $this->info("Processing Invoice [{$currentNum}/{$total}] - ID: #{$invoice->id}");

            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $invoice->id],
                    ['PO Reference', $invoice->po_reference ?? '-'],
                    ['Supplier', $invoice->supplier?->name ?? 'Unknown'],
                    ['Amount', format_money($invoice->amount)],
                    ['Status', $invoice->status instanceof \BackedEnum ? $invoice->status->value : ($invoice->status ?? '-')],
                    ['Current Invoice Date', $invoice->invoice_date?->format('Y-m-d') ?? 'None'],
                ]
            );

            $invoiceNumberPrompt = "Enter supplier invoice number for Invoice #{$invoice->id} (type 'skip' to skip, 'cancel' to exit)";
            $invoiceNumber = $this->ask($invoiceNumberPrompt);
            $trimmedNumber = trim((string) $invoiceNumber);
            $lower = strtolower($trimmedNumber);

            if ($lower === 'cancel') {
                $this->warn('Batch update cancelled by user.');
                $cancelled = true;
                break;
            }

            if ($lower === 'skip' || $trimmedNumber === '') {
                $this->warn("Skipped Invoice #{$invoice->id}.");
                $skipped++;

                continue;
            }

            if (strlen($trimmedNumber) > 255) {
                $this->error("Invoice number cannot exceed 255 characters. Skipping Invoice #{$invoice->id}.");
                $skipped++;

                continue;
            }

            $defaultDate = $invoice->invoice_date?->format('Y-m-d')
                ?? $invoice->order_received_date?->format('Y-m-d')
                ?? now()->toDateString();

            $datePrompt = 'Enter invoice date (YYYY-MM-DD)';
            $dateInput = $this->ask($datePrompt, $defaultDate);
            $trimmedDate = trim((string) $dateInput);

            try {
                $parsedDate = Carbon::createFromFormat('Y-m-d', $trimmedDate)->startOfDay();
            } catch (\Throwable) {
                $this->error("Invalid date format '{$trimmedDate}'. Expected YYYY-MM-DD. Skipping Invoice #{$invoice->id}.");
                $skipped++;

                continue;
            }

            $invoice->update([
                'invoice_number' => $trimmedNumber,
                'invoice_date' => $parsedDate->toDateString(),
            ]);

            $this->info("Successfully updated Invoice #{$invoice->id} (Invoice Number: {$trimmedNumber}, Date: {$parsedDate->toDateString()}).");
            $updated++;
        }

        $this->line('');
        $this->line(str_repeat('=', 50));
        $this->info('Vendor Invoices Batch Update Summary:');
        $this->line("Total blank invoices found: {$total}");
        $this->line("Successfully updated:       {$updated}");
        $this->line("Skipped:                    {$skipped}");
        if ($cancelled) {
            $remaining = $total - $updated - $skipped;
            $this->warn("Cancelled early. Remaining unprocessed: {$remaining}");
        }
        $this->line(str_repeat('=', 50));

        return self::SUCCESS;
    }
}
