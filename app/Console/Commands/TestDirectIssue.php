<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;

class TestDirectIssue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:direct-issue {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test direct issue without model binding';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        
        $this->info("Probando emisión directa para factura ID: $id");
        
        // Obtener la factura directamente
        $invoice = Invoice::find($id);
        
        if (!$invoice) {
            $this->error("❌ Factura no encontrada con ID: $id");
            return;
        }
        
        $this->info("✅ Factura encontrada:");
        $this->info("ID: " . $invoice->id);
        $this->info("Número: " . $invoice->invoice_number);
        $this->info("Estado: " . $invoice->status);
        
        if ($invoice->status !== 'Draft') {
            $this->error("❌ La factura no está en estado Draft. Estado actual: " . $invoice->status);
            return;
        }
        
        $this->info("✅ La factura está en estado Draft, procediendo a emitir...");
        
        // Actualizar el estado
        $invoice->update([
            'status' => 'Issued',
            'issue_date' => $invoice->issue_date ?? now(),
        ]);
        
        $this->info("✅ Factura emitida exitosamente. Nuevo estado: " . $invoice->status);
        
        // Verificar el estado actualizado
        $updatedInvoice = Invoice::find($id);
        $this->info("Estado verificado: " . $updatedInvoice->status);
    }
}
