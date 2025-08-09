<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProductServiceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SystemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Test route without auth
Route::post('/test-invoices/{invoice}/issue', [InvoiceController::class, 'issue']);

// Test route with auth but different approach
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/test-invoices-auth/{invoice}/issue', [InvoiceController::class, 'issue']);
});





// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // Products/Services
    Route::apiResource('products-services', ProductServiceController::class);

    // Invoices
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    
    // Test route without auth
    Route::post('/test-invoices/{invoice}/issue', [InvoiceController::class, 'issue']);

    // Reports
    Route::get('/reports/sales', [ReportController::class, 'sales']);
    Route::get('/reports/customers', [ReportController::class, 'customers']);
    Route::get('/reports/products', [ReportController::class, 'products']);
    Route::get('/reports/monthly-sales', [ReportController::class, 'monthlySales']);

    // System
    Route::get('/system/info', [SystemController::class, 'info']);
    Route::get('/system/health', [SystemController::class, 'health']);
});

 