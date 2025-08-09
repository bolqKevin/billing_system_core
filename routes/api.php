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
Route::get('/test-invoices/{invoice}/pdf', [InvoiceController::class, 'generatePDF']);

// Public system routes
Route::get('/system/health', [SystemController::class, 'health']);

// Public test email route
Route::post('/test-email', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'subject' => 'nullable|string',
        'message' => 'nullable|string',
    ]);

    try {
        // Get SMTP settings
        $smtpSettings = \App\Models\Setting::where('company_id', 1)
            ->whereIn('code', [
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                'smtp_encryption', 'smtp_from_email', 'smtp_from_name'
            ])
            ->pluck('value', 'code')
            ->toArray();

        // Configure mail settings
        config([
            'mail.mailers.smtp.host' => $smtpSettings['smtp_host'],
            'mail.mailers.smtp.port' => $smtpSettings['smtp_port'],
            'mail.mailers.smtp.username' => $smtpSettings['smtp_username'] ?? '',
            'mail.mailers.smtp.password' => $smtpSettings['smtp_password'] ?? '',
            'mail.mailers.smtp.encryption' => $smtpSettings['smtp_encryption'] ?? 'tls',
            'mail.from.address' => $smtpSettings['smtp_from_email'] ?? 'noreply@example.com',
            'mail.from.name' => $smtpSettings['smtp_from_name'] ?? 'Sistema de Facturación',
        ]);

        $subject = $request->subject ?? 'Prueba de Email - Sistema de Facturación';
        $message = $request->message ?? 'Este es un correo de prueba para verificar la configuración SMTP.';

        \Illuminate\Support\Facades\Mail::send([], [], function ($mailMessage) use ($request, $subject, $message) {
            $mailMessage->to($request->email)
                    ->subject($subject)
                    ->html($message);
        });

        return response()->json([
            'success' => true,
            'message' => 'Correo enviado exitosamente',
            'data' => [
                'recipient' => $request->email,
                'subject' => $subject,
            ],
        ]);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Test email failed', [
            'error' => $e->getMessage(),
            'email' => $request->email,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error enviando correo: ' . $e->getMessage(),
        ], 500);
    }
});

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
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'generatePDF']);
    Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);
    Route::post('/invoices/{invoice}/send-email-no-pdf', [InvoiceController::class, 'sendEmailWithoutPDF']);
    
    
    // Test route without auth
    Route::post('/test-invoices/{invoice}/issue', [InvoiceController::class, 'issue']);

    // Reports
    Route::get('/reports/sales', [ReportController::class, 'sales']);
    Route::get('/reports/customers', [ReportController::class, 'customers']);
    Route::get('/reports/products', [ReportController::class, 'products']);
    Route::get('/reports/monthly-sales', [ReportController::class, 'monthlySales']);

    // System
    Route::get('/system/info', [SystemController::class, 'info']);
    Route::get('/system/settings', [SystemController::class, 'getSettings']);
    Route::put('/system/settings', [SystemController::class, 'updateSettings']);
    Route::get('/system/company-info', [SystemController::class, 'getCompanyInfo']);
});

 