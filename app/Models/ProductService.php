<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductService extends Model
{
    use HasFactory;

    protected $table = 'products_services';

    protected $fillable = [
        'code',
        'name_description',
        'type',
        'unit_measure',
        'unit_price',
        'tax_rate',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the invoice details for this product/service.
     */
    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class, 'product_service_id');
    }

    /**
     * Scope a query to only include active products/services.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope a query to only include inactive products/services.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'Inactive');
    }

    /**
     * Scope a query to only include products.
     */
    public function scopeProducts($query)
    {
        return $query->where('type', 'Product');
    }

    /**
     * Scope a query to only include services.
     */
    public function scopeServices($query)
    {
        return $query->where('type', 'Service');
    }
} 