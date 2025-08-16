<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProductServiceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\UserController;
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

// Password reset routes
Route::post('/password/send-otp', [AuthController::class, 'sendPasswordResetOtp']);
Route::post('/password/verify-otp-only', [AuthController::class, 'verifyOtpOnly']);
Route::post('/password/verify-otp', [AuthController::class, 'verifyOtpAndResetPassword']);
Route::post('/password/increment-attempts', [AuthController::class, 'incrementOtpAttempts']);

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

    // Customers - Solo lectura para facturadores
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:view_customer');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:view_customer');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:create_customer');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:update_customer');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:delete_customer');
    Route::delete('/customers/{customer}/permanent', [CustomerController::class, 'permanentDelete'])->middleware('permission:delete_customer');

    // Products/Services - Solo lectura para facturadores
    Route::get('/products-services', [ProductServiceController::class, 'index'])->middleware('permission:view_product');
    Route::get('/products-services/{productService}', [ProductServiceController::class, 'show'])->middleware('permission:view_product');
    Route::post('/products-services', [ProductServiceController::class, 'store'])->middleware('permission:create_product');
    Route::put('/products-services/{productService}', [ProductServiceController::class, 'update'])->middleware('permission:update_product');
    Route::delete('/products-services/{productService}', [ProductServiceController::class, 'destroy'])->middleware('permission:delete_product');
    Route::delete('/products-services/{productService}/permanent', [ProductServiceController::class, 'permanentDelete'])->middleware('permission:delete_product');

    // Users - Solo administradores
    Route::get('/users/roles', [UserController::class, 'getRoles'])->middleware('permission:manage_users');
    Route::get('/users/stats', [UserController::class, 'getStats'])->middleware('permission:manage_users');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:manage_users');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:manage_users');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:manage_users');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:manage_users');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:manage_users');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:manage_users');
    Route::post('/users/check-email', [UserController::class, 'checkEmailExists'])->middleware('permission:manage_users');
    Route::post('/users/check-username', [UserController::class, 'checkUsernameExists'])->middleware('permission:manage_users');

    // Invoices - Permisos específicos para facturadores
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:view_invoice');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:create_invoice');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:view_invoice');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->middleware('permission:update_invoice');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:delete_invoice');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->middleware('permission:update_invoice');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:cancel_invoice');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'generatePDF'])->middleware('permission:generate_pdf');
    Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail'])->middleware('permission:send_email');
    Route::post('/invoices/{invoice}/send-email-no-pdf', [InvoiceController::class, 'sendEmailWithoutPDF'])->middleware('permission:send_email');
    
    // Test route without auth
    Route::post('/test-invoices/{invoice}/issue', [InvoiceController::class, 'issue']);

    // Reports - Solo lectura para facturadores
    Route::get('/reports/sales', [ReportController::class, 'sales'])->middleware('permission:view_reports');
    Route::get('/reports/customers', [ReportController::class, 'customers'])->middleware('permission:view_reports');
    Route::get('/reports/products', [ReportController::class, 'products'])->middleware('permission:view_reports');
    Route::get('/reports/monthly-sales', [ReportController::class, 'monthlySales'])->middleware('permission:view_reports');

    // System - Solo administradores
    Route::get('/system/info', [SystemController::class, 'info'])->middleware('permission:configure_system');
    Route::get('/system/settings', [SystemController::class, 'getSettings'])->middleware('permission:configure_system');
    Route::put('/system/settings', [SystemController::class, 'updateSettings'])->middleware('permission:configure_system');
    Route::get('/system/company-info', [SystemController::class, 'getCompanyInfo'])->middleware('permission:configure_system');
    Route::put('/system/company-info', [SystemController::class, 'updateCompanyInfo'])->middleware('permission:configure_system');

    // Audit - Solo administradores
    Route::get('/audit/movements', [AuditController::class, 'getMovementLogs'])->middleware('permission:view_logs');
    Route::get('/audit/logins', [AuditController::class, 'getLoginLogs'])->middleware('permission:view_logs');
    Route::get('/audit/users', [AuditController::class, 'getUsers'])->middleware('permission:view_logs');
    Route::get('/audit/statistics', [AuditController::class, 'getStatistics'])->middleware('permission:view_logs');
    Route::post('/audit/export', [AuditController::class, 'exportLogs'])->middleware('permission:view_logs');
    
    // Rutas de prueba sin middleware de permisos
    Route::get('/audit/test-movements', [AuditController::class, 'getMovementLogs']);
    Route::get('/audit/test-logins', [AuditController::class, 'getLoginLogs']);
});

// Rutas públicas temporales para debug (con filtro por compañía)
Route::get('/audit/public-logins', [AuditController::class, 'getPublicLoginLogs']);
Route::get('/audit/public-movements', [AuditController::class, 'getPublicMovementLogs']);

// Rutas públicas temporales para reportes (con filtro por compañía)
Route::get('/reports/public-sales', [ReportController::class, 'getPublicSales']);
Route::get('/reports/public-customers', [ReportController::class, 'getPublicCustomers']);
Route::get('/reports/public-products', [ReportController::class, 'getPublicProducts']);
Route::get('/reports/public-monthly-sales', [ReportController::class, 'getPublicMonthlySales']);

 