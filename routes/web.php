<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Sistema de Facturación - API Laravel',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});

// Ruta de healthcheck simple para Railway
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString()
    ]);
});

// Ruta de login para el middleware de autenticación
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized'], 401);
})->name('login');
