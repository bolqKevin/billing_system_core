<?php

namespace App\Services;

use SendGrid;
use SendGrid\Mail\Mail;
use SendGrid\Mail\Attachment;
use Illuminate\Support\Facades\Log;

class SendGridService
{
    private $sendGrid;
    private $fromEmail;
    private $fromName;

    public function __construct()
    {
        // Credenciales quemadas para pruebas
        $apiKey = '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';
        $this->fromEmail = 'bolq3kevin@gmail.com';
        $this->fromName = 'Sistema de Facturación';

        $this->sendGrid = new SendGrid($apiKey);
    }

    /**
     * Enviar email simple sin archivos adjuntos
     */
    public function sendSimpleEmail($to, $subject, $message, $isHtml = true)
    {
        try {
            $email = new Mail();
            $email->setFrom($this->fromEmail, $this->fromName);
            $email->setSubject($subject);
            $email->addTo($to);

            if ($isHtml) {
                $email->addContent("text/html", $message);
            } else {
                $email->addContent("text/plain", $message);
            }

            $response = $this->sendGrid->send($email);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                Log::info('SendGrid email sent successfully', [
                    'to' => $to,
                    'subject' => $subject,
                    'status_code' => $response->statusCode()
                ]);
                return true;
            } else {
                Log::error('SendGrid email failed', [
                    'to' => $to,
                    'subject' => $subject,
                    'status_code' => $response->statusCode(),
                    'body' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('SendGrid email exception', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Enviar email con archivos adjuntos
     */
    public function sendEmailWithAttachments($to, $subject, $message, $attachments = [], $isHtml = true)
    {
        try {
            $email = new Mail();
            $email->setFrom($this->fromEmail, $this->fromName);
            $email->setSubject($subject);
            $email->addTo($to);

            if ($isHtml) {
                $email->addContent("text/html", $message);
            } else {
                $email->addContent("text/plain", $message);
            }

            // Agregar archivos adjuntos
            foreach ($attachments as $attachment) {
                if (file_exists($attachment['path'])) {
                    $sendGridAttachment = new Attachment();
                    $sendGridAttachment->setContent(base64_encode(file_get_contents($attachment['path'])));
                    $sendGridAttachment->setType($attachment['mime'] ?? 'application/octet-stream');
                    $sendGridAttachment->setFilename($attachment['name'] ?? basename($attachment['path']));
                    $sendGridAttachment->setDisposition("attachment");
                    
                    $email->addAttachment($sendGridAttachment);
                } else {
                    Log::warning('Attachment file not found', ['path' => $attachment['path']]);
                }
            }

            $response = $this->sendGrid->send($email);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                Log::info('SendGrid email with attachments sent successfully', [
                    'to' => $to,
                    'subject' => $subject,
                    'attachments_count' => count($attachments),
                    'status_code' => $response->statusCode()
                ]);
                return true;
            } else {
                Log::error('SendGrid email with attachments failed', [
                    'to' => $to,
                    'subject' => $subject,
                    'status_code' => $response->statusCode(),
                    'body' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('SendGrid email with attachments exception', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Enviar email con PDF de factura
     */
    public function sendInvoiceEmail($to, $subject, $message, $pdfPath, $xmlInvoicePath = null, $xmlResponsePath = null)
    {
        $attachments = [
            [
                'path' => $pdfPath,
                'name' => basename($pdfPath),
                'mime' => 'application/pdf'
            ]
        ];

        if ($xmlInvoicePath && file_exists($xmlInvoicePath)) {
            $attachments[] = [
                'path' => $xmlInvoicePath,
                'name' => basename($xmlInvoicePath),
                'mime' => 'application/xml'
            ];
        }

        if ($xmlResponsePath && file_exists($xmlResponsePath)) {
            $attachments[] = [
                'path' => $xmlResponsePath,
                'name' => basename($xmlResponsePath),
                'mime' => 'application/xml'
            ];
        }

        return $this->sendEmailWithAttachments($to, $subject, $message, $attachments);
    }
}
