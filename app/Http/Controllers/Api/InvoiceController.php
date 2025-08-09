<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\ProductService;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use TCPDF;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = $user ? $user->company_id : null;
        
        $query = Invoice::with(['customer', 'creationUser', 'details.productService']);
        
        // Filtrar por empresa del usuario
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

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
            'payment_method' => 'required|in:Cash,Transfer,Check',
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
                'company_id' => $request->user()->company_id,
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
            'payment_method' => 'required|in:Cash,Transfer,Check',
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

    /**
     * Generate PDF for invoice
     */
    public function generatePDF($invoiceId)
    {
        // Cargar la factura manualmente con todas las relaciones necesarias
        $invoice = Invoice::with(['customer', 'details.productService', 'creationUser'])->find($invoiceId);
        
        if (!$invoice) {
            return response()->json([
                'message' => 'Factura no encontrada',
            ], 404);
        }

        // Crear nueva instancia de TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurar información del documento
        $pdf->SetCreator('Sistema de Facturación');
        $pdf->SetAuthor('Mi Empresa S.A.');
        $pdf->SetTitle('Factura ' . $invoice->invoice_number);
        $pdf->SetSubject('Factura');
        
        // Configurar márgenes
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Configurar saltos de página automáticos
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Configurar fuente con soporte para caracteres especiales
        $pdf->SetFont('dejavusans', '', 10);
        
        // Agregar página
        $pdf->AddPage();
        
        // Obtener información de la empresa del usuario autenticado
        $user = Auth::user();
        $company = $user ? $user->company : Company::active()->first();
        
        // Información de la empresa
        $companyName = $company ? $company->business_name : 'Mi Empresa S.A.';
        $companyAddress = $company ? $company->address : 'San José, Costa Rica';
        $companyPhone = $company ? $company->phone : '+506 2222-2222';
        $companyEmail = $company ? $company->email : 'info@miempresa.com';
        $companyTaxId = $company ? $company->legal_id : '3-101-123456';
        
        // Encabezado
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, $companyName, 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 5, $companyAddress, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Tel: ' . $companyPhone . ' | Email: ' . $companyEmail, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Cédula Jurídica: ' . $companyTaxId, 0, 1, 'C');
        $pdf->Ln(10);
        
        // Información de la factura y cliente
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(95, 10, 'FACTURA', 0, 0);
        $pdf->Cell(95, 10, 'CLIENTE', 0, 1, 'R');
        
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(95, 5, 'Número: ' . $invoice->invoice_number, 0, 0);
        $pdf->Cell(95, 5, $invoice->customer ? $invoice->customer->name_business_name : 'Cliente no especificado', 0, 1, 'R');
        
        $pdf->Cell(95, 5, 'Fecha: ' . \Carbon\Carbon::parse($invoice->issue_date)->format('d/m/Y'), 0, 0);
        if ($invoice->customer && $invoice->customer->identification_number) {
            $pdf->Cell(95, 5, 'Cédula: ' . $invoice->customer->identification_number, 0, 1, 'R');
        } else {
            $pdf->Cell(95, 5, '', 0, 1, 'R');
        }
        
        $pdf->Cell(95, 5, 'Estado: ' . ($invoice->status == 'Draft' ? 'Borrador' : ($invoice->status == 'Issued' ? 'Emitida' : 'Anulada')), 0, 0);
        if ($invoice->customer && $invoice->customer->address) {
            $pdf->Cell(95, 5, 'Dirección: ' . $invoice->customer->address, 0, 1, 'R');
        } else {
            $pdf->Cell(95, 5, '', 0, 1, 'R');
        }
        
        $pdf->Ln(10);
        
        // Título de la tabla
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 10, 'DETALLE DE LA FACTURA', 0, 1);
        
        // Encabezados de la tabla
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell(10, 8, '#', 1, 0, 'C');
        $pdf->Cell(70, 8, 'Descripción', 1, 0, 'L');
        $pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Precio Unit.', 1, 0, 'R');
        $pdf->Cell(20, 8, 'Descuento', 1, 0, 'R');
        $pdf->Cell(25, 8, 'Subtotal', 1, 0, 'R');
        $pdf->Cell(25, 8, 'Total', 1, 1, 'R');
        
        // Detalles de la factura
        $pdf->SetFont('dejavusans', '', 9);
        foreach ($invoice->details as $index => $detail) {
            $pdf->Cell(10, 6, $index + 1, 1, 0, 'C');
            $pdf->Cell(70, 6, $detail->productService ? $detail->productService->name_description : 'Producto no especificado', 1, 0, 'L');
            $pdf->Cell(20, 6, number_format($detail->quantity, 2), 1, 0, 'C');
            $pdf->Cell(25, 6, '₡' . number_format($detail->unit_price, 2), 1, 0, 'R');
            $pdf->Cell(20, 6, $detail->item_discount > 0 ? '₡' . number_format($detail->item_discount, 2) : '-', 1, 0, 'R');
            $pdf->Cell(25, 6, '₡' . number_format($detail->item_subtotal, 2), 1, 0, 'R');
            $pdf->Cell(25, 6, '₡' . number_format($detail->item_total, 2), 1, 1, 'R');
        }
        
        $pdf->Ln(5);
        
        // Totales
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(125, 6, 'Subtotal:', 0, 0, 'R');
        $pdf->Cell(25, 6, '₡' . number_format($invoice->subtotal, 2), 0, 1, 'R');
        
        if ($invoice->total_discount > 0) {
            $pdf->Cell(125, 6, 'Descuentos:', 0, 0, 'R');
            $pdf->Cell(25, 6, '₡' . number_format($invoice->total_discount, 2), 0, 1, 'R');
        }
        
        $pdf->Cell(125, 6, 'Impuesto (13%):', 0, 0, 'R');
        $pdf->Cell(25, 6, '₡' . number_format($invoice->total_tax, 2), 0, 1, 'R');
        
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(125, 8, 'TOTAL:', 0, 0, 'R');
        $pdf->Cell(25, 8, '₡' . number_format($invoice->grand_total, 2), 0, 1, 'R');
        
        $pdf->Ln(10);
        
        // Información de pago
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 8, 'INFORMACIÓN DE PAGO', 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 5, 'Condición de Venta: ' . ($invoice->sale_condition == 'Cash' ? 'Contado' : 'Crédito (' . $invoice->credit_days . ' días)'), 0, 1);
        $pdf->Cell(0, 5, 'Método de Pago: ' . ($invoice->payment_method == 'Cash' ? 'Contado' : ($invoice->payment_method == 'Transfer' ? 'Transferencia' : ($invoice->payment_method == 'Check' ? 'Cheque' : 'Otro'))), 0, 1);
        
        if ($invoice->due_date) {
            $pdf->Cell(0, 5, 'Fecha de Vencimiento: ' . \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y'), 0, 1);
        }
        
        // Observaciones
        if ($invoice->observations) {
            $pdf->Ln(5);
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->Cell(0, 8, 'OBSERVACIONES', 0, 1);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->MultiCell(0, 5, $invoice->observations, 0, 'L');
        }
        
        // Pie de página
        $pdf->SetY(-30);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->Cell(0, 5, $companyName, 0, 1, 'C');
        $pdf->Cell(0, 5, $companyAddress . ' | Tel: ' . $companyPhone . ' | Email: ' . $companyEmail, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Cédula Jurídica: ' . $companyTaxId, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Factura generada el ' . \Carbon\Carbon::now()->format('d/m/Y H:i:s'), 0, 1, 'C');
        
        // Generar el PDF
        $pdfContent = $pdf->Output('factura-' . $invoice->invoice_number . '.pdf', 'S');
        
        // Retornar como respuesta
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="factura-' . $invoice->invoice_number . '.pdf"');
    }
} 