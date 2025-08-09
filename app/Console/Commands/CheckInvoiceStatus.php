<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;

class CheckInvoiceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of all invoices in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $invoices = Invoice::all();
        
        $this->info('Estado de todas las facturas:');
        $this->table(
            ['ID', 'Número', 'Estado', 'Cliente'],
            $invoices->map(function ($invoice) {
                return [
                    $invoice->id,
                    $invoice->invoice_number,
                    $invoice->status,
                    $invoice->customer->name_business_name ?? 'N/A'
                ];
            })->toArray()
        );
        
        // Show statistics
        $statusCounts = $invoices->groupBy('status')->map->count();
        $this->info('Estadísticas por estado:');
        foreach ($statusCounts as $status => $count) {
            $this->line("$status: $count facturas");
        }
    }
}
