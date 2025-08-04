<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get sales report
     */
    public function sales(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $query = Invoice::with(['customer', 'creationUser'])
            ->where('status', 'Issued')
            ->whereBetween('issue_date', [$request->start_date, $request->end_date]);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $invoices = $query->orderBy('issue_date', 'desc')->get();

        $summary = [
            'total_invoices' => $invoices->count(),
            'total_amount' => $invoices->sum('grand_total'),
            'total_tax' => $invoices->sum('total_tax'),
            'total_discount' => $invoices->sum('total_discount'),
            'average_invoice' => $invoices->count() > 0 ? $invoices->sum('grand_total') / $invoices->count() : 0,
        ];

        return response()->json([
            'summary' => $summary,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Get customers report
     */
    public function customers(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = Customer::with(['invoices' => function ($q) use ($request) {
            if ($request->has('start_date')) {
                $q->where('issue_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $q->where('issue_date', '<=', $request->end_date);
            }
            $q->where('status', 'Issued');
        }]);

        $customers = $query->where('status', 'Active')->get();

        $customerStats = $customers->map(function ($customer) {
            $invoices = $customer->invoices;
            return [
                'id' => $customer->id,
                'name_business_name' => $customer->name_business_name,
                'identification_number' => $customer->identification_number,
                'total_invoices' => $invoices->count(),
                'total_amount' => $invoices->sum('grand_total'),
                'last_invoice_date' => $invoices->max('issue_date'),
            ];
        })->sortByDesc('total_amount');

        return response()->json($customerStats);
    }

    /**
     * Get products/services report
     */
    public function products(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|in:Product,Service',
        ]);

        $query = DB::table('invoice_details')
            ->join('products_services', 'invoice_details.product_service_id', '=', 'products_services.id')
            ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'Issued')
            ->whereBetween('invoices.issue_date', [$request->start_date, $request->end_date]);

        if ($request->has('type')) {
            $query->where('products_services.type', $request->type);
        }

        $products = $query->select(
            'products_services.id',
            'products_services.code',
            'products_services.name_description',
            'products_services.type',
            'products_services.unit_measure',
            DB::raw('SUM(invoice_details.quantity) as total_quantity'),
            DB::raw('SUM(invoice_details.item_subtotal) as total_subtotal'),
            DB::raw('SUM(invoice_details.item_tax) as total_tax'),
            DB::raw('SUM(invoice_details.item_total) as total_amount'),
            DB::raw('COUNT(DISTINCT invoices.id) as invoice_count')
        )
        ->groupBy(
            'products_services.id',
            'products_services.code',
            'products_services.name_description',
            'products_services.type',
            'products_services.unit_measure'
        )
        ->orderBy('total_amount', 'desc')
        ->get();

        return response()->json($products);
    }

    /**
     * Get monthly sales report
     */
    public function monthlySales(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
        ]);

        $monthlyData = Invoice::where('status', 'Issued')
            ->whereYear('issue_date', $request->year)
            ->selectRaw("
                MONTH(issue_date) as month,
                SUM(grand_total) as total_sales,
                COUNT(*) as invoice_count,
                SUM(total_tax) as total_tax,
                SUM(total_discount) as total_discount
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Fill missing months with zero values
        $completeData = collect();
        for ($month = 1; $month <= 12; $month++) {
            $monthData = $monthlyData->where('month', $month)->first();
            $completeData->push([
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'total_sales' => $monthData ? $monthData->total_sales : 0,
                'invoice_count' => $monthData ? $monthData->invoice_count : 0,
                'total_tax' => $monthData ? $monthData->total_tax : 0,
                'total_discount' => $monthData ? $monthData->total_discount : 0,
            ]);
        }

        return response()->json($completeData);
    }
} 