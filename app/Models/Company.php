<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'company_name',
        'business_name',
        'legal_id',
        'address',
        'phone',
        'email',
        'invoice_current_consecutive',
        'invoice_prefix',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the settings for this company.
     */
    public function settings()
    {
        return $this->hasMany(Setting::class);
    }

    /**
     * Get the users for this company.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the customers for this company.
     */
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get the products/services for this company.
     */
    public function productsServices()
    {
        return $this->hasMany(ProductService::class);
    }

    /**
     * Get the invoices for this company.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope a query to only include active companies.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope a query to only include inactive companies.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'Inactive');
    }
} 