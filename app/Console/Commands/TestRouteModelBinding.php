<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class TestRouteModelBinding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:route-model-binding {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test route model binding for Invoice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        
        $this->info("Probando modelo binding para factura ID: $id");
        
        // Simular el proceso de modelo binding
        $invoice = Invoice::find($id);
        
        if (!$invoice) {
            $this->error("❌ Factura no encontrada con ID: $id");
            return;
        }
        
        $this->info("✅ Factura encontrada:");
        $this->info("ID: " . $invoice->id);
        $this->info("Número: " . $invoice->invoice_number);
        $this->info("Estado: " . $invoice->status);
        $this->info("Existe: " . ($invoice->exists ? 'SÍ' : 'NO'));
        
        // Probar el método issue directamente
        $this->info("Probando método issue directamente...");
        
        // Simular la lógica del método issue
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
    }
}
