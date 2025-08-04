<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    use HasFactory;

    protected $table = 'invoice_details';

    protected $fillable = [
        'invoice_id',
        'product_service_id',
        'quantity',
        'unit_price',
        'item_discount',
        'item_subtotal',
        'item_tax',
        'item_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'item_discount' => 'decimal:2',
        'item_subtotal' => 'decimal:2',
        'item_tax' => 'decimal:2',
        'item_total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * Get the invoice that owns the detail.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the product/service for this detail.
     */
    public function productService()
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    /**
     * Calculate the item totals.
     */
    public function calculateTotals()
    {
        $this->item_subtotal = $this->quantity * $this->unit_price;
        
        // Get tax rate from product/service
        $taxRate = $this->productService ? $this->productService->tax_rate : 12.00;
        $this->item_tax = $this->item_subtotal * ($taxRate / 100);
        
        $this->item_total = $this->item_subtotal - $this->item_discount + $this->item_tax;
        
        $this->save();
    }
} 