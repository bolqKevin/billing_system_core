<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSend extends Model
{
    use HasFactory;

    protected $table = 'email_sends';

    protected $fillable = [
        'invoice_id',
        'recipient',
        'subject',
        'email_body',
        'send_status',
        'error_message',
        'sent_date',
    ];

    protected $casts = [
        'sent_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the invoice that owns the email send.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
} 