<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class TestIssueRoute extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:issue-route {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the issue route specifically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        
        $this->info("Probando ruta de issue para factura ID: $id");
        
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
        
        // Probar diferentes variaciones de la URL
        $urls = [
            "http://localhost:8000/api/invoices/$id/issue",
            "http://localhost:8000/api/test-invoices/$id/issue",
            "http://localhost:8000/api/test-invoices-auth/$id/issue",
        ];
        
        foreach ($urls as $index => $url) {
            $this->info("Probando URL $index: $url");
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($url, []);
            
            $this->info("Código: " . $response->status() . ", Cuerpo: " . $response->body());
            $this->info("---");
        }
    }
}
