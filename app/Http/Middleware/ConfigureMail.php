<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Setting;

class ConfigureMail
{
    public function handle(Request $request, Closure $next)
    {
        // Get SMTP settings from database
        $smtpSettings = Setting::where('company_id', 1)
            ->whereIn('code', [
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                'smtp_encryption', 'smtp_from_email', 'smtp_from_name'
            ])
            ->pluck('value', 'code')
            ->toArray();

        if (!empty($smtpSettings['smtp_host'])) {
            // Configure mail settings
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
                'mail.from.address' => $smtpSettings['smtp_from_email'],
                'mail.from.name' => $smtpSettings['smtp_from_name'],
            ]);
        }

        return $next($request);
    }
}
