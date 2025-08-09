<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function index(Request $request)
    {
        try {
            $companyId = $request->user()->company_id;
            $currentMonth = Carbon::now()->startOfMonth();
            $previousMonth = Carbon::now()->subMonth()->startOfMonth();

            // Sales data
            $currentMonthSales = Invoice::where('company_id', $companyId)
                ->where('status', 'Issued')
                ->whereBetween('created_at', [$currentMonth, Carbon::now()])
                ->sum('grand_total');

            $previousMonthSales = Invoice::where('company_id', $companyId)
                ->where('status', 'Issued')
                ->whereBetween('created_at', [$previousMonth, $currentMonth])
                ->sum('grand_total');

            $growthPercentage = $previousMonthSales > 0 
                ? (($currentMonthSales - $previousMonthSales) / $previousMonthSales) * 100 
                : 0;

            // Invoices data
            $totalIssued = Invoice::where('company_id', $companyId)
                ->where('status', 'Issued')
                ->count();

            $draftInvoices = Invoice::where('company_id', $companyId)
                ->where('status', 'Draft')
                ->count();

            $cancelledInvoices = Invoice::where('company_id', $companyId)
                ->where('status', 'Cancelled')
                ->count();

            // Customers data
            $totalCustomers = Customer::where('company_id', $companyId)->count();
            
            $newCustomersThisMonth = Customer::where('company_id', $companyId)
                ->whereBetween('created_at', [$currentMonth, Carbon::now()])
                ->count();

            // Products/Services data
            $totalProducts = ProductService::where('company_id', $companyId)
                ->where('type', 'Product')
                ->count();

            $totalServices = ProductService::where('company_id', $companyId)
                ->where('type', 'Service')
                ->count();

            // Recent invoices
            $recentInvoices = Invoice::where('company_id', $companyId)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'customer_name' => $invoice->customer->commercial_name ?? $invoice->customer->name_business_name ?? 'Cliente no encontrado',
                        'grand_total' => $invoice->grand_total,
                        'status' => $invoice->status,
                        'created_at' => $invoice->created_at->toISOString(),
                    ];
                });

            // Top selling products
            $topSellingProducts = DB::table('invoice_details')
                ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
                ->join('products_services', 'invoice_details.product_service_id', '=', 'products_services.id')
                ->where('invoices.company_id', $companyId)
                ->where('invoices.status', 'Issued')
                ->select(
                    'products_services.name_description',
                    'products_services.type',
                    DB::raw('SUM(invoice_details.quantity) as total_quantity'),
                    DB::raw('SUM(invoice_details.quantity * invoice_details.unit_price) as total_amount')
                )
                ->groupBy('products_services.id', 'products_services.name_description', 'products_services.type')
                ->orderBy('total_amount', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'name_description' => $item->name_description,
                        'type' => $item->type,
                        'total_quantity' => $item->total_quantity,
                        'total_amount' => $item->total_amount,
                    ];
                });

            // Monthly sales chart (last 6 months)
            $monthlySalesChart = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthSales = Invoice::where('company_id', $companyId)
                    ->where('status', 'Issued')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('grand_total');

                $monthlySalesChart[] = [
                    'month' => $month->format('M Y'),
                    'total_sales' => $monthSales,
                ];
            }

            return response()->json([
                'sales' => [
                    'current_month' => $currentMonthSales,
                    'previous_month' => $previousMonthSales,
                    'growth_percentage' => round($growthPercentage, 2),
                ],
                'invoices' => [
                    'total_issued' => $totalIssued,
                    'draft' => $draftInvoices,
                    'cancelled' => $cancelledInvoices,
                ],
                'customers' => [
                    'total' => $totalCustomers,
                    'new_this_month' => $newCustomersThisMonth,
                ],
                'products_services' => [
                    'total_products' => $totalProducts,
                    'total_services' => $totalServices,
                ],
                'recent_invoices' => $recentInvoices,
                'top_selling_products' => $topSellingProducts,
                'monthly_sales_chart' => $monthlySalesChart,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar datos del dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
} 