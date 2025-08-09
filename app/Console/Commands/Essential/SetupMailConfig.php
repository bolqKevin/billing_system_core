<?php

namespace App\Console\Commands\Essential;

use Illuminate\Console\Command;
use App\Models\Setting;

class SetupMailConfig extends Command
{
    protected $signature = 'setup:mail-config';
    protected $description = 'Setup mail configuration from database settings';

    public function handle()
    {
        $this->info("=== CONFIGURACIÓN DE MAIL ===");
        
        // Get SMTP settings from database
        $smtpSettings = Setting::where('company_id', 1)
            ->whereIn('code', [
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                'smtp_encryption', 'smtp_from_email', 'smtp_from_name'
            ])
            ->pluck('value', 'code')
            ->toArray();

        if (empty($smtpSettings['smtp_host'])) {
            $this->error("❌ No hay configuración SMTP en la base de datos");
            return 1;
        }

        $this->info("Configuración SMTP encontrada:");
        $this->table(['Configuración', 'Valor'], [
            ['smtp_host', $smtpSettings['smtp_host'] ?? 'NO CONFIGURADO'],
            ['smtp_port', $smtpSettings['smtp_port'] ?? 'NO CONFIGURADO'],
            ['smtp_username', $smtpSettings['smtp_username'] ?? 'NO CONFIGURADO'],
            ['smtp_password', $smtpSettings['smtp_password'] ? 'CONFIGURADO' : 'NO CONFIGURADO'],
            ['smtp_encryption', $smtpSettings['smtp_encryption'] ?? 'NO CONFIGURADO'],
            ['smtp_from_email', $smtpSettings['smtp_from_email'] ?? 'NO CONFIGURADO'],
            ['smtp_from_name', $smtpSettings['smtp_from_name'] ?? 'NO CONFIGURADO'],
        ]);

        // Configure mail settings globally
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $smtpSettings['smtp_host'],
            'mail.mailers.smtp.port' => $smtpSettings['smtp_port'],
            'mail.mailers.smtp.username' => $smtpSettings['smtp_username'],
            'mail.mailers.smtp.password' => $smtpSettings['smtp_password'],
            'mail.mailers.smtp.encryption' => $smtpSettings['smtp_encryption'],
            'mail.from.address' => $smtpSettings['smtp_from_email'],
            'mail.from.name' => $smtpSettings['smtp_from_name'],
        ]);

        $this->info("✅ Configuración de mail aplicada");
        
        // Verify configuration
        $this->info("\nVerificando configuración:");
        $this->info("Default mailer: " . config('mail.default'));
        $this->info("SMTP Host: " . config('mail.mailers.smtp.host'));
        $this->info("SMTP Port: " . config('mail.mailers.smtp.port'));
        $this->info("SMTP Username: " . config('mail.mailers.smtp.username'));
        $this->info("SMTP Encryption: " . config('mail.mailers.smtp.encryption'));
        $this->info("From Address: " . config('mail.from.address'));
        $this->info("From Name: " . config('mail.from.name'));

        $this->info("\nAhora puedes probar el envío de correos:");
        $this->info("php artisan diagnostic:test-email-sending 1 tu-email@ejemplo.com");
        
        return 0;
    }
}
