<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;

class TestModelBinding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:model-binding {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test model binding for Invoice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        
        $this->info("Probando modelo binding para factura ID: $id");
        
        // Probar búsqueda directa
        $invoice = Invoice::find($id);
        
        if (!$invoice) {
            $this->error("❌ Factura no encontrada con ID: $id");
            return;
        }
        
        $this->info("✅ Factura encontrada:");
        $this->info("ID: " . $invoice->id);
        $this->info("Número: " . $invoice->invoice_number);
        $this->info("Estado: " . $invoice->status);
        $this->info("Cliente: " . ($invoice->customer->name_business_name ?? 'N/A'));
        
        // Probar búsqueda por número
        $invoiceByNumber = Invoice::where('invoice_number', $invoice->invoice_number)->first();
        
        if ($invoiceByNumber) {
            $this->info("✅ Factura encontrada por número: " . $invoiceByNumber->invoice_number);
        } else {
            $this->error("❌ Factura no encontrada por número");
        }
    }
}
