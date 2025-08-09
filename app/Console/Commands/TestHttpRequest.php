<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestHttpRequest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:http-request {invoice_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the HTTP request for issuing an invoice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $invoiceId = $this->argument('invoice_id');
        
        $this->info("Probando petición HTTP para emitir factura ID: $invoiceId");
        
        // Primero hacer login para obtener token
        $loginResponse = Http::post("http://localhost:8000/api/login", [
            'email' => 'admin@construccionesgriegass.com',
            'password' => 'admin123'
        ]);
        
        if (!$loginResponse->successful()) {
            $this->error("❌ Error en login: " . $loginResponse->body());
            return;
        }
        
        $token = $loginResponse->json('token');
        $this->info("✅ Login exitoso, token obtenido");
        
        // Simular la petición HTTP con autenticación
        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post("http://localhost:8000/api/invoices/$invoiceId/issue", []);
        
        // También probar la ruta de prueba sin auth
        $testResponse = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post("http://localhost:8000/api/test-invoices/$invoiceId/issue", []);
        
        $this->info("Respuesta con auth - Código: " . $response->status() . ", Cuerpo: " . $response->body());
        $this->info("Respuesta sin auth - Código: " . $testResponse->status() . ", Cuerpo: " . $testResponse->body());
        
        $this->info("Código de respuesta: " . $response->status());
        $this->info("Cuerpo de respuesta: " . $response->body());
        
        if ($response->successful()) {
            $this->info("✅ Petición exitosa");
        } else {
            $this->error("❌ Petición fallida");
        }
    }
}
