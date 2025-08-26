<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConfigureMail
{
    public function handle(Request $request, Closure $next)
    {
        // SOLO usar variables de entorno de Railway - NUNCA buscar en base de datos
        if (env('MAIL_HOST') && env('MAIL_PASSWORD') && env('MAIL_USERNAME')) {
            // Use environment variables (Railway configuration) - ÚNICA FUENTE
            config([
                'mail.default' => env('MAIL_MAILER', 'smtp'),
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => env('MAIL_HOST'),
                'mail.mailers.smtp.port' => env('MAIL_PORT', 587),
                'mail.mailers.smtp.username' => env('MAIL_USERNAME'),
                'mail.mailers.smtp.password' => env('MAIL_PASSWORD'),
                'mail.mailers.smtp.encryption' => env('MAIL_ENCRYPTION', 'tls'),
                'mail.mailers.smtp.verify_peer' => false,
                'mail.mailers.smtp.verify_peer_name' => false,
                'mail.mailers.smtp.allow_self_signed' => true,
                'mail.mailers.smtp.timeout' => 30,
                'mail.from.address' => env('MAIL_FROM_ADDRESS'),
                'mail.from.name' => env('MAIL_FROM_NAME'),
            ]);
            
            \Illuminate\Support\Facades\Log::info('RAILWAY SMTP Configuration applied (ENVIRONMENT ONLY)', [
                'source' => 'environment_variables_only',
                'host' => env('MAIL_HOST'),
                'port' => env('MAIL_PORT'),
                'username' => env('MAIL_USERNAME'),
                'encryption' => env('MAIL_ENCRYPTION'),
                'from_email' => env('MAIL_FROM_ADDRESS'),
                'from_name' => env('MAIL_FROM_NAME'),
            ]);
        } else {
            // Si no hay variables de entorno configuradas, solo log de advertencia
            \Illuminate\Support\Facades\Log::warning('No SMTP configuration found in environment variables - email functionality will not work', [
                'env_host' => env('MAIL_HOST'),
                'env_password' => env('MAIL_PASSWORD') ? 'SET' : 'NOT_SET',
                'env_username' => env('MAIL_USERNAME'),
                'message' => 'Configure MAIL_HOST, MAIL_PASSWORD, and MAIL_USERNAME in Railway environment variables',
            ]);
        }

        return $next($request);
    }
}
