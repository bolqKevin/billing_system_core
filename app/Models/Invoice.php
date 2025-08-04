<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'issue_date',
        'customer_id',
        'subtotal',
        'total_discount',
        'total_tax',
        'grand_total',
        'payment_method',
        'sale_condition',
        'credit_days',
        'observations',
        'status',
        'cancellation_reason',
        'xml_invoice',
        'xml_treasury_response',
        'creation_user_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'credit_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the invoice.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user that created the invoice.
     */
    public function creationUser()
    {
        return $this->belongsTo(User::class, 'creation_user_id');
    }

    /**
     * Get the invoice details for this invoice.
     */
    public function details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    /**
     * Get the email sends for this invoice.
     */
    public function emailSends()
    {
        return $this->hasMany(EmailSend::class);
    }

    /**
     * Scope a query to only include draft invoices.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'Draft');
    }

    /**
     * Scope a query to only include issued invoices.
     */
    public function scopeIssued($query)
    {
        return $query->where('status', 'Issued');
    }

    /**
     * Scope a query to only include cancelled invoices.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'Cancelled');
    }

    /**
     * Calculate the invoice totals.
     */
    public function calculateTotals()
    {
        $subtotal = $this->details->sum('item_subtotal');
        $totalDiscount = $this->details->sum('item_discount');
        $totalTax = $this->details->sum('item_tax');
        $grandTotal = $subtotal - $totalDiscount + $totalTax;

        $this->update([
            'subtotal' => $subtotal,
            'total_discount' => $totalDiscount,
            'total_tax' => $totalTax,
            'grand_total' => $grandTotal,
        ]);
    }
} 