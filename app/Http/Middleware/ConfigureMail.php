<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Setting;

class ConfigureMail
{
    public function handle(Request $request, Closure $next)
    {
        // Check if environment variables are set (Railway)
        if (env('MAIL_HOST') && env('MAIL_PASSWORD')) {
            // Use environment variables (Railway configuration)
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
            
            \Illuminate\Support\Facades\Log::info('Railway SMTP Configuration applied from environment variables', [
                'host' => env('MAIL_HOST'),
                'port' => env('MAIL_PORT'),
                'username' => env('MAIL_USERNAME'),
                'encryption' => env('MAIL_ENCRYPTION'),
                'from_email' => env('MAIL_FROM_ADDRESS'),
                'from_name' => env('MAIL_FROM_NAME'),
            ]);
        } else {
            // Fallback to database configuration (local development)
            $smtpSettings = Setting::where('company_id', 1)
                ->whereIn('code', [
                    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                    'smtp_encryption', 'smtp_from_email', 'smtp_from_name'
                ])
                ->pluck('value', 'code')
                ->toArray();

            if (!empty($smtpSettings['smtp_host'])) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.transport' => 'smtp',
                    'mail.mailers.smtp.host' => $smtpSettings['smtp_host'],
                    'mail.mailers.smtp.port' => $smtpSettings['smtp_port'],
                    'mail.mailers.smtp.username' => $smtpSettings['smtp_username'],
                    'mail.mailers.smtp.password' => $smtpSettings['smtp_password'],
                    'mail.mailers.smtp.encryption' => $smtpSettings['smtp_encryption'],
                    'mail.mailers.smtp.verify_peer' => false,
                    'mail.mailers.smtp.verify_peer_name' => false,
                    'mail.mailers.smtp.allow_self_signed' => true,
                    'mail.mailers.smtp.timeout' => 30,
                    'mail.from.address' => $smtpSettings['smtp_from_email'],
                    'mail.from.name' => $smtpSettings['smtp_from_name'],
                ]);
                
                \Illuminate\Support\Facades\Log::info('Database SMTP Configuration applied', [
                    'host' => $smtpSettings['smtp_host'],
                    'port' => $smtpSettings['smtp_port'],
                    'username' => $smtpSettings['smtp_username'],
                    'encryption' => $smtpSettings['smtp_encryption'],
                    'from_email' => $smtpSettings['smtp_from_email'],
                    'from_name' => $smtpSettings['smtp_from_name'],
                ]);
            }
        }

        return $next($request);
    }
}
