<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['customer', 'creationUser', 'details.productService']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name_business_name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('issue_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('issue_date', '<=', $request->end_date);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(15);

        // Transform data for frontend
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->customer_name = $invoice->customer->name_business_name ?? '';
            return $invoice;
        });

        return response()->json($invoices);
    }

    /**
     * Store a newly created invoice
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'payment_method' => 'required|in:Cash,Transfer,Card,Check,Other',
            'sale_condition' => 'required|in:Cash,Credit',
            'credit_days' => 'nullable|integer|min:0',
            'observations' => 'nullable|string',
            'status' => 'nullable|in:Draft,Issued,Cancelled',
            'items' => 'required|array|min:1',
            'items.*.product_service_id' => 'required|exists:products_services,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.item_discount' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Generate invoice number
            $lastInvoice = Invoice::orderBy('id', 'desc')->first();
            $nextNumber = $lastInvoice ? intval(substr($lastInvoice->invoice_number, 4)) + 1 : 1;
            $invoiceNumber = 'INV-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Set default values
            $status = $request->status ?? 'Draft';
            $issueDate = $request->issue_date ?? now()->format('Y-m-d');

            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $request->customer_id,
                'issue_date' => $issueDate,
                'due_date' => $request->due_date,
                'payment_method' => $request->payment_method,
                'sale_condition' => $request->sale_condition,
                'credit_days' => $request->credit_days ?? 0,
                'observations' => $request->observations,
                'status' => ucfirst($status),
                'creation_user_id' => $request->user()->id,
                'subtotal' => $request->subtotal ?? 0,
                'total_tax' => $request->total_tax ?? 0,
                'total_discount' => $request->total_discount ?? 0,
                'grand_total' => $request->grand_total ?? 0,
            ]);

            // Create invoice details
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($request->items as $item) {
                $productService = ProductService::find($item['product_service_id']);
                
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['item_discount'] ?? 0;
                $itemTax = ($itemSubtotal - $itemDiscount) * ($productService->tax_rate / 100);
                $itemTotal = $itemSubtotal - $itemDiscount + $itemTax;

                InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'product_service_id' => $item['product_service_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'item_discount' => $itemDiscount,
                    'item_subtotal' => $itemSubtotal,
                    'item_tax' => $itemTax,
                    'item_total' => $itemTotal,
                ]);

                $subtotal += $itemSubtotal;
                $totalTax += $itemTax;
                $totalDiscount += $itemDiscount;
            }

            // Update invoice totals if not provided
            if (!$request->subtotal) {
                $invoice->update([
                    'subtotal' => $subtotal,
                    'total_tax' => $totalTax,
                    'total_discount' => $totalDiscount,
                    'grand_total' => $subtotal - $totalDiscount + $totalTax,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Factura creada exitosamente',
                'data' => $invoice->load(['customer', 'details.productService']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la factura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified invoice
     */
    public function show(Invoice $invoice)
    {
        return response()->json($invoice->load(['customer', 'details.productService', 'creationUser']));
    }

    /**
     * Update the specified invoice
     */
    public function update(Request $request, Invoice $invoice)
    {
        if ($invoice->status !== 'Draft') {
            return response()->json([
                'message' => 'Solo se pueden editar facturas en estado borrador',
            ], 400);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'payment_method' => 'required|in:Cash,Transfer,Card,Check,Other',
            'sale_condition' => 'required|in:Cash,Credit',
            'credit_days' => 'nullable|integer|min:0',
            'observations' => 'nullable|string',
            'status' => 'nullable|in:Draft,Issued,Cancelled',
            'items' => 'required|array|min:1',
            'items.*.product_service_id' => 'required|exists:products_services,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.item_discount' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Update invoice
            $updateData = [
                'customer_id' => $request->customer_id,
                'issue_date' => $request->issue_date,
                'due_date' => $request->due_date,
                'payment_method' => $request->payment_method,
                'sale_condition' => $request->sale_condition,
                'credit_days' => $request->credit_days ?? 0,
                'observations' => $request->observations,
            ];

            // Update status if provided
            if ($request->has('status')) {
                $updateData['status'] = ucfirst($request->status);
            }

            $invoice->update($updateData);

            // Delete existing details
            $invoice->details()->delete();

            // Create new invoice details
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($request->items as $item) {
                $productService = ProductService::find($item['product_service_id']);
                
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['item_discount'] ?? 0;
                $itemTax = ($itemSubtotal - $itemDiscount) * ($productService->tax_rate / 100);
                $itemTotal = $itemSubtotal - $itemDiscount + $itemTax;

                InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'product_service_id' => $item['product_service_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'item_discount' => $itemDiscount,
                    'item_subtotal' => $itemSubtotal,
                    'item_tax' => $itemTax,
                    'item_total' => $itemTotal,
                ]);

                $subtotal += $itemSubtotal;
                $totalTax += $itemTax;
                $totalDiscount += $itemDiscount;
            }

            // Update invoice totals
            $invoice->update([
                'subtotal' => $subtotal,
                'total_tax' => $totalTax,
                'total_discount' => $totalDiscount,
                'grand_total' => $subtotal - $totalDiscount + $totalTax,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Factura actualizada exitosamente',
                'data' => $invoice->load(['customer', 'details.productService']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la factura: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified invoice
     */
    public function destroy(Invoice $invoice)
    {
        if ($invoice->status !== 'Draft') {
            return response()->json([
                'message' => 'Solo se pueden eliminar facturas en estado borrador',
            ], 400);
        }

        $invoice->delete();

        return response()->json([
            'message' => 'Factura eliminada exitosamente',
        ]);
    }

    /**
     * Issue an invoice
     */
    public function issue($invoiceId)
    {
        // Obtener la factura manualmente
        $invoice = Invoice::find($invoiceId);
        
        Log::info('Método issue llamado', [
            'requested_id' => $invoiceId,
            'invoice_found' => $invoice ? 'SÍ' : 'NO',
            'invoice_id' => $invoice ? $invoice->id : 'null',
            'invoice_number' => $invoice ? $invoice->invoice_number : 'null',
            'current_status' => $invoice ? $invoice->status : 'null',
            'request_url' => request()->url(),
            'request_method' => request()->method(),
            'user_authenticated' => Auth::check(),
            'user_id' => Auth::id()
        ]);
        
        // Verificar si la factura existe
        if (!$invoice) {
            Log::error('Factura no encontrada', [
                'requested_id' => $invoiceId
            ]);
            
            return response()->json([
                'message' => 'Factura no encontrada',
            ], 404);
        }
        
        Log::info('Intentando emitir factura', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'current_status' => $invoice->status
        ]);
        
        if ($invoice->status !== 'Draft') {
            return response()->json([
                'message' => 'Solo se pueden emitir facturas en estado borrador. Estado actual: ' . $invoice->status,
            ], 400);
        }

        $invoice->update([
            'status' => 'Issued',
            'issue_date' => $invoice->issue_date ?? now(),
        ]);

        return response()->json([
            'message' => 'Factura emitida exitosamente',
            'data' => $invoice,
        ]);
    }

    /**
     * Cancel an invoice
     */
    public function cancel(Request $request, Invoice $invoice)
    {
        if (!in_array($invoice->status, ['Draft', 'Issued'])) {
            return response()->json([
                'message' => 'No se puede cancelar una factura en este estado',
            ], 400);
        }

        $request->validate([
            'cancellation_reason' => 'required|string',
        ]);

        $invoice->update([
            'status' => 'Cancelled',
            'cancellation_reason' => $request->cancellation_reason,
        ]);

        return response()->json([
            'message' => 'Factura cancelada exitosamente',
            'data' => $invoice,
        ]);
    }
} 