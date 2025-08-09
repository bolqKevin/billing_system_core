<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class TestIssueInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:test-issue {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test issuing a specific invoice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        $invoice = Invoice::find($id);
        
        if (!$invoice) {
            $this->error("Factura con ID $id no encontrada");
            return;
        }
        
        $this->info("Probando emisión de factura:");
        $this->info("ID: " . $invoice->id);
        $this->info("Número: " . $invoice->invoice_number);
        $this->info("Estado actual: '" . $invoice->status . "'");
        $this->info("Tipo de estado: " . gettype($invoice->status));
        $this->info("Longitud del estado: " . strlen($invoice->status));
        $this->info("Estado recortado: '" . trim($invoice->status) . "'");
        $this->info("¿Es igual a 'Draft'?: " . ($invoice->status === 'Draft' ? 'SÍ' : 'NO'));
        
        // Simular la lógica del controlador
        if ($invoice->status !== 'Draft') {
            $this->error("La factura no está en estado Draft. Estado actual: '" . $invoice->status . "'");
            return;
        }
        
        $this->info("La factura está en estado Draft, procediendo a emitir...");
        
        // Actualizar el estado
        $invoice->update([
            'status' => 'Issued',
            'issue_date' => $invoice->issue_date ?? now(),
        ]);
        
        $this->info("Factura emitida exitosamente. Nuevo estado: " . $invoice->status);
    }
}
