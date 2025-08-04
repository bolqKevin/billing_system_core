<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
        public function index(Request $request)
    {
        try {
            // Return a simple response for now
            return response()->json([
                'sales' => [
                    'current_month' => 0,
                    'previous_month' => 0,
                    'growth_percentage' => 0,
                ],
                'invoices' => [
                    'total_issued' => 0,
                    'draft' => 0,
                    'cancelled' => 0,
                ],
                'customers' => [
                    'total' => 0,
                    'new_this_month' => 0,
                ],
                'products_services' => [
                    'total_products' => 0,
                    'total_services' => 0,
                ],
                'recent_invoices' => [],
                'top_selling_products' => [],
                'monthly_sales_chart' => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar datos del dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
} 