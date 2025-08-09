<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class TestAuthMiddleware extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:auth-middleware';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test authentication middleware';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Probando middleware de autenticación");
        
        // Probar login
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
        
        // Probar petición autenticada
        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->get("http://localhost:8000/api/invoices");
        
        $this->info("Respuesta GET /invoices - Código: " . $response->status());
        
        if ($response->successful()) {
            $this->info("✅ Petición autenticada exitosa");
            $data = $response->json();
            $this->info("Total de facturas: " . ($data['total'] ?? 'N/A'));
        } else {
            $this->error("❌ Petición autenticada fallida: " . $response->body());
        }
    }
}
