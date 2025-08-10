<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\ProductService;
use App\Models\Company;
use App\Models\Setting;
use App\Models\EmailSend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        
        // Filter by user's company
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
    public function destroy($invoiceId)
    {
        // Buscar la factura manualmente
        $invoice = Invoice::find($invoiceId);
        
        if (!$invoice) {
            Log::error('Factura no encontrada para eliminar', ['invoice_id' => $invoiceId]);
            return response()->json([
                'message' => 'Factura no encontrada',
            ], 404);
        }

        Log::info('Intentando eliminar factura', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'current_status' => $invoice->status
        ]);

        if ($invoice->status !== 'Draft') {
            Log::warning('Intento de eliminar factura no borrador', [
                'invoice_id' => $invoice->id,
                'current_status' => $invoice->status
            ]);
            
            return response()->json([
                'message' => 'Solo se pueden eliminar facturas en estado borrador. Estado actual: ' . $invoice->status,
            ], 400);
        }

        try {
            $invoice->delete();
            
            Log::info('Factura eliminada exitosamente', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number
            ]);

            return response()->json([
                'message' => 'Factura eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar factura', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => 'Error al eliminar la factura: ' . $e->getMessage(),
            ], 500);
        }
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
    public function cancel(Request $request, $invoiceId)
    {
        // Buscar la factura manualmente
        $invoice = Invoice::find($invoiceId);
        
        if (!$invoice) {
            Log::error('Factura no encontrada', ['invoice_id' => $invoiceId]);
            return response()->json([
                'message' => 'Factura no encontrada',
            ], 404);
        }
        
        // Debug: Log the invoice status
        Log::info('Intentando cancelar factura', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'current_status' => $invoice->status,
            'current_status_type' => gettype($invoice->status),
            'current_status_length' => strlen($invoice->status),
            'allowed_statuses' => ['Draft', 'Issued'],
            'request_data' => $request->all(),
            'invoice_attributes' => $invoice->getAttributes()
        ]);

        // Verificación más detallada del estado
        $currentStatus = trim($invoice->status);
        $allowedStatuses = ['Draft', 'Issued'];
        
        Log::info('Verificación detallada del estado', [
            'current_status' => $currentStatus,
            'current_status_quoted' => "'{$currentStatus}'",
            'is_in_array' => in_array($currentStatus, $allowedStatuses),
            'allowed_statuses' => $allowedStatuses
        ]);

        if (!in_array($currentStatus, $allowedStatuses)) {
            Log::warning('Factura no puede ser cancelada - estado incorrecto', [
                'invoice_id' => $invoice->id,
                'current_status' => $currentStatus,
                'current_status_quoted' => "'{$currentStatus}'",
                'allowed_statuses' => $allowedStatuses
            ]);
            
            return response()->json([
                'message' => 'No se puede cancelar una factura en este estado. Estado actual: ' . $currentStatus,
            ], 400);
        }

        try {
            $request->validate([
                'cancellation_reason' => 'required|string',
            ]);
            
            Log::info('Validación exitosa', [
                'cancellation_reason' => $request->cancellation_reason,
                'reason_length' => strlen($request->cancellation_reason)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'Error de validación: ' . implode(', ', collect($e->errors())->flatten()->toArray()),
            ], 422);
        }

        $invoice->update([
            'status' => 'Cancelled',
            'cancellation_reason' => $request->cancellation_reason,
        ]);

        Log::info('Factura cancelada exitosamente', [
            'invoice_id' => $invoice->id,
            'new_status' => 'Cancelled'
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
        // Upload the invoice manually with all the necessary relationships
        $invoice = Invoice::with(['customer', 'details.productService', 'creationUser'])->find($invoiceId);
        
        if (!$invoice) {
            return response()->json([
                'message' => 'Factura no encontrada',
            ], 404);
        }

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configure document information
        $pdf->SetCreator('Sistema de Facturación');
        $pdf->SetAuthor('Mi Empresa S.A.');
        $pdf->SetTitle('Factura ' . $invoice->invoice_number);
        $pdf->SetSubject('Factura');
        
        // margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // automatic page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // support for special characters
        $pdf->SetFont('dejavusans', '', 10);
        
        $pdf->AddPage();
        
        // Get company information from the authenticated user
        $user = Auth::user();
        $company = $user ? $user->company : Company::active()->first();
        
        // Company information & Placeholder
        $companyName = $company ? $company->business_name : 'Mi Empresa S.A.';
        $companyAddress = $company ? $company->address : 'San José, Costa Rica';
        $companyPhone = $company ? $company->phone : '+506 2222-2222';
        $companyEmail = $company ? $company->email : 'info@miempresa.com';
        $companyTaxId = $company ? $company->legal_id : '3-101-123456';
        
        // Header
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, $companyName, 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 5, $companyAddress, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Tel: ' . $companyPhone . ' | Email: ' . $companyEmail, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Cédula Jurídica: ' . $companyTaxId, 0, 1, 'C');
        $pdf->Ln(10);
        
        // Invoice and customer information
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
        
        // Table title
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 10, 'DETALLE DE LA FACTURA', 0, 1);
        
        // Table headers
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell(10, 8, '#', 1, 0, 'C');
        $pdf->Cell(70, 8, 'Descripción', 1, 0, 'L');
        $pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Precio Unit.', 1, 0, 'R');
        $pdf->Cell(20, 8, 'Descuento', 1, 0, 'R');
        $pdf->Cell(25, 8, 'Subtotal', 1, 0, 'R');
        $pdf->Cell(25, 8, 'Total', 1, 1, 'R');
        
        // Invoice details
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
        
        // Totals
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
        
        // Payment information
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 8, 'INFORMACIÓN DE PAGO', 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 5, 'Condición de Venta: ' . ($invoice->sale_condition == 'Cash' ? 'Contado' : 'Crédito (' . $invoice->credit_days . ' días)'), 0, 1);
        $pdf->Cell(0, 5, 'Método de Pago: ' . ($invoice->payment_method == 'Cash' ? 'Contado' : ($invoice->payment_method == 'Transfer' ? 'Transferencia' : ($invoice->payment_method == 'Check' ? 'Cheque' : 'Otro'))), 0, 1);
        
        if ($invoice->due_date) {
            $pdf->Cell(0, 5, 'Fecha de Vencimiento: ' . \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y'), 0, 1);
        }
        
        // Observations
        if ($invoice->observations) {
            $pdf->Ln(5);
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->Cell(0, 8, 'OBSERVACIONES', 0, 1);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->MultiCell(0, 5, $invoice->observations, 0, 'L');
        }
        
        // Footer
        $pdf->SetY(-30);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->Cell(0, 5, $companyName, 0, 1, 'C');
        $pdf->Cell(0, 5, $companyAddress . ' | Tel: ' . $companyPhone . ' | Email: ' . $companyEmail, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Cédula Jurídica: ' . $companyTaxId, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Factura generada el ' . \Carbon\Carbon::now()->format('d/m/Y H:i:s'), 0, 1, 'C');
        
        // Generate the PDF
        $pdfContent = $pdf->Output('factura-' . $invoice->invoice_number . '.pdf', 'S');
        
        // Return as response
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="factura-' . $invoice->invoice_number . '.pdf"');
    }

    /**
     * Send invoice by email
     */
    public function sendEmail(Request $request, $invoiceId)
    {
        $invoice = Invoice::with(['customer', 'details.productService'])->find($invoiceId);
        
        if (!$invoice) {
            return response()->json([
                'message' => 'Factura no encontrada',
            ], 404);
        }

        $request->validate([
            'recipient_email' => 'required|email',
            'subject' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        try {
            // Get SMTP settings from database
            $user = Auth::user();
            $companyId = $user ? $user->company_id : 1;
            
            $smtpSettings = Setting::where('company_id', $companyId)
                ->whereIn('code', [
                    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                    'smtp_encryption', 'smtp_from_email', 'smtp_from_name'
                ])
                ->pluck('value', 'code')
                ->toArray();

            // Validate SMTP settings
            if (empty($smtpSettings['smtp_host']) || empty($smtpSettings['smtp_port'])) {
                return response()->json([
                    'message' => 'Configuración SMTP no encontrada. Por favor, configure el servidor de correo en Configuración > Email.',
                ], 400);
            }

            // Configure mail settings dynamically
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => $smtpSettings['smtp_host'],
                'mail.mailers.smtp.port' => $smtpSettings['smtp_port'],
                'mail.mailers.smtp.username' => $smtpSettings['smtp_username'] ?? '',
                'mail.mailers.smtp.password' => $smtpSettings['smtp_password'] ?? '',
                'mail.mailers.smtp.encryption' => $smtpSettings['smtp_encryption'] ?? 'tls',
                'mail.mailers.smtp.verify_peer' => false,
                'mail.mailers.smtp.verify_peer_name' => false,
                'mail.mailers.smtp.allow_self_signed' => true,
                'mail.from.address' => $smtpSettings['smtp_from_email'] ?? 'noreply@example.com',
                'mail.from.name' => $smtpSettings['smtp_from_name'] ?? 'Sistema de Facturación',
            ]);

            // Generate PDF
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Configure document information
            $pdf->SetCreator('Sistema de Facturación');
            $pdf->SetAuthor('Mi Empresa S.A.');
            $pdf->SetTitle('Factura ' . $invoice->invoice_number);
            $pdf->SetSubject('Factura');
            
            // margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // automatic page breaks
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // support for special characters
            $pdf->SetFont('dejavusans', '', 10);
            
            $pdf->AddPage();
            
            // Get company information
            $company = $user ? $user->company : Company::active()->first();
            
            // Company information & Placeholder
            $companyName = $company ? $company->business_name : 'Mi Empresa S.A.';
            $companyAddress = $company ? $company->address : 'San José, Costa Rica';
            $companyPhone = $company ? $company->phone : '+506 2222-2222';
            $companyEmail = $company ? $company->email : 'info@miempresa.com';
            $companyTaxId = $company ? $company->legal_id : '3-101-123456';
            
            // Header
            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->Cell(0, 10, $companyName, 0, 1, 'C');
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->Cell(0, 5, $companyAddress, 0, 1, 'C');
            $pdf->Cell(0, 5, 'Tel: ' . $companyPhone . ' | Email: ' . $companyEmail, 0, 1, 'C');
            $pdf->Cell(0, 5, 'Cédula Jurídica: ' . $companyTaxId, 0, 1, 'C');
            $pdf->Ln(10);
            
            // Invoice and customer information
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
            
            // Table title
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->Cell(0, 10, 'DETALLE DE LA FACTURA', 0, 1);
            
            // Table headers
            $pdf->SetFont('dejavusans', 'B', 9);
            $pdf->Cell(10, 8, '#', 1, 0, 'C');
            $pdf->Cell(70, 8, 'Descripción', 1, 0, 'L');
            $pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C');
            $pdf->Cell(25, 8, 'Precio Unit.', 1, 0, 'R');
            $pdf->Cell(20, 8, 'Descuento', 1, 0, 'R');
            $pdf->Cell(25, 8, 'Subtotal', 1, 0, 'R');
            $pdf->Cell(25, 8, 'Total', 1, 1, 'R');
            
            // Invoice details
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
            
            // Totals
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
            
            // Payment information
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->Cell(0, 8, 'INFORMACIÓN DE PAGO', 0, 1);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->Cell(0, 5, 'Condición de Venta: ' . ($invoice->sale_condition == 'Cash' ? 'Contado' : 'Crédito (' . $invoice->credit_days . ' días)'), 0, 1);
            $pdf->Cell(0, 5, 'Método de Pago: ' . ($invoice->payment_method == 'Cash' ? 'Contado' : ($invoice->payment_method == 'Transfer' ? 'Transferencia' : ($invoice->payment_method == 'Check' ? 'Cheque' : 'Otro'))), 0, 1);
            
            if ($invoice->due_date) {
                $pdf->Cell(0, 5, 'Fecha de Vencimiento: ' . \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y'), 0, 1);
            }
            
            // Observations
            if ($invoice->observations) {
                $pdf->Ln(5);
                $pdf->SetFont('dejavusans', 'B', 11);
                $pdf->Cell(0, 8, 'OBSERVACIONES', 0, 1);
                $pdf->SetFont('dejavusans', '', 10);
                $pdf->MultiCell(0, 5, $invoice->observations, 0, 'L');
            }
            
            // Footer
            $pdf->SetY(-30);
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->Cell(0, 5, $companyName, 0, 1, 'C');
            $pdf->Cell(0, 5, $companyAddress . ' | Tel: ' . $companyPhone . ' | Email: ' . $companyEmail, 0, 1, 'C');
            $pdf->Cell(0, 5, 'Cédula Jurídica: ' . $companyTaxId, 0, 1, 'C');
            $pdf->Cell(0, 5, 'Factura generada el ' . \Carbon\Carbon::now()->format('d/m/Y H:i:s'), 0, 1, 'C');
            
            // Generate the PDF
            $pdfContent = $pdf->Output('factura-' . $invoice->invoice_number . '.pdf', 'S');
            
            // Save PDF to temporary file
            $tempPath = storage_path('app/temp/factura-' . $invoice->invoice_number . '-' . time() . '.pdf');
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            file_put_contents($tempPath, $pdfContent);
            
            // Check if PDF was created successfully
            if (!file_exists($tempPath)) {
                throw new \Exception('No se pudo crear el archivo PDF');
            }
            
            $fileSize = filesize($tempPath);
            Log::info('PDF generated successfully', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'pdf_path' => $tempPath,
                'file_size' => $fileSize . ' bytes'
            ]);

            // Generate XML files for Costa Rica electronic invoicing
            $xmlInvoicePath = $this->generateInvoiceXML($invoice, $company);
            $xmlResponsePath = $this->generateResponseXML($invoice, $company);

            // Prepare email data
            $subject = $request->subject ?? 'Factura ' . $invoice->invoice_number . ' - ' . $companyName;
            $message = $request->message ?? 'Adjunto encontrará la factura ' . $invoice->invoice_number . ' por un monto de ₡' . number_format($invoice->grand_total, 2) . '.\n\nGracias por su preferencia.\n\nSaludos cordiales,\n' . $companyName;

            // Send email with all attachments
            try {
                Mail::send([], [], function ($mailMessage) use ($request, $subject, $message, $tempPath, $xmlInvoicePath, $xmlResponsePath, $invoice) {
                    $mailMessage->to($request->recipient_email)
                            ->subject($subject)
                            ->html($message)
                            ->attach($tempPath, [
                                'as' => 'factura-' . $invoice->invoice_number . '.pdf',
                                'mime' => 'application/pdf',
                            ])
                            ->attach($xmlInvoicePath, [
                                'as' => 'factura-' . $invoice->invoice_number . '.xml',
                                'mime' => 'application/xml',
                            ])
                            ->attach($xmlResponsePath, [
                                'as' => 'respuesta-' . $invoice->invoice_number . '.xml',
                                'mime' => 'application/xml',
                            ]);
                });
                
                Log::info('Email sent successfully with all attachments', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'recipient' => $request->recipient_email,
                    'subject' => $subject,
                    'pdf_size' => $fileSize . ' bytes',
                    'xml_invoice_size' => filesize($xmlInvoicePath) . ' bytes',
                    'xml_response_size' => filesize($xmlResponsePath) . ' bytes'
                ]);
                
            } catch (\Exception $e) {
                Log::error('Email sending failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                    'recipient' => $request->recipient_email
                ]);
                
                // Clean up temporary files
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                if (file_exists($xmlInvoicePath)) {
                    unlink($xmlInvoicePath);
                }
                if (file_exists($xmlResponsePath)) {
                    unlink($xmlResponsePath);
                }
                
                throw new \Exception('Error enviando correo: ' . $e->getMessage());
            }

            // Log successful email send
            Log::info('Invoice email sent successfully', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient' => $request->recipient_email,
                'subject' => $subject,
            ]);

            // Record email send
            EmailSend::create([
                'invoice_id' => $invoice->id,
                'recipient' => $request->recipient_email,
                'subject' => $subject,
                'email_body' => $message,
                'send_status' => 'Sent',
                'sent_date' => now(),
            ]);

            // Clean up temporary files
            unlink($tempPath);
            unlink($xmlInvoicePath);
            unlink($xmlResponsePath);

            return response()->json([
                'message' => 'Factura enviada por correo exitosamente',
                'data' => [
                    'recipient' => $request->recipient_email,
                    'subject' => $subject,
                ],
            ]);

        } catch (\Exception $e) {
            // Record failed email send
            EmailSend::create([
                'invoice_id' => $invoice->id,
                'recipient' => $request->recipient_email,
                'subject' => $subject ?? 'Error',
                'email_body' => $message ?? '',
                'send_status' => 'Error',
                'error_message' => $e->getMessage(),
                'sent_date' => now(),
            ]);

            return response()->json([
                'message' => 'Error enviando correo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send invoice email without PDF (for testing)
     */
    public function sendEmailWithoutPDF(Request $request, $invoiceId)
    {
        try {
            $invoice = Invoice::with(['customer', 'details.productService'])->find($invoiceId);
            
            if (!$invoice) {
                return response()->json(['message' => 'Factura no encontrada'], 404);
            }

            $request->validate([
                'recipient_email' => 'required|email',
                'subject' => 'nullable|string',
                'message' => 'nullable|string',
            ]);

            // Get SMTP settings
            $smtpSettings = Setting::where('company_id', 1)
                ->whereIn('code', [
                    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                    'smtp_encryption', 'smtp_from_email', 'smtp_from_name'
                ])
                ->pluck('value', 'code')
                ->toArray();

            // Configure mail settings
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => $smtpSettings['smtp_host'],
                'mail.mailers.smtp.port' => $smtpSettings['smtp_port'],
                'mail.mailers.smtp.username' => $smtpSettings['smtp_username'] ?? '',
                'mail.mailers.smtp.password' => $smtpSettings['smtp_password'] ?? '',
                'mail.mailers.smtp.encryption' => $smtpSettings['smtp_encryption'] ?? 'tls',
                'mail.mailers.smtp.verify_peer' => false,
                'mail.mailers.smtp.verify_peer_name' => false,
                'mail.mailers.smtp.allow_self_signed' => true,
                'mail.from.address' => $smtpSettings['smtp_from_email'] ?? 'noreply@example.com',
                'mail.from.name' => $smtpSettings['smtp_from_name'] ?? 'Sistema de Facturación',
            ]);

            // Get company info
            $company = Company::first();
            $companyName = $company ? $company->business_name : 'Mi Empresa';

            // Prepare email data
            $subject = $request->subject ?? 'Factura ' . $invoice->invoice_number . ' - ' . $companyName;
            $message = $request->message ?? 'Adjunto encontrará la factura ' . $invoice->invoice_number . ' por un monto de ₡' . number_format($invoice->grand_total, 2) . '.\n\nGracias por su preferencia.\n\nSaludos cordiales,\n' . $companyName;

            // Send email without PDF
            Mail::send([], [], function ($mailMessage) use ($request, $subject, $message, $invoice) {
                $mailMessage->to($request->recipient_email)
                        ->subject($subject)
                        ->html($message);
            });

            // Record email send
            EmailSend::create([
                'invoice_id' => $invoice->id,
                'recipient' => $request->recipient_email,
                'subject' => $subject,
                'email_body' => $message,
                'send_status' => 'Sent',
                'sent_date' => now(),
            ]);

            Log::info('Invoice email sent successfully (without PDF)', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipient' => $request->recipient_email,
                'subject' => $subject,
            ]);

            return response()->json([
                'message' => 'Factura enviada por correo exitosamente (sin PDF)',
                'data' => [
                    'recipient' => $request->recipient_email,
                    'subject' => $subject,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice email sending failed (without PDF)', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'recipient' => $request->recipient_email ?? 'N/A'
            ]);

            return response()->json([
                'message' => 'Error enviando correo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send invoice email with PDF as link instead of attachment
     */

    
    /**
     * Generate HTML content for invoice PDF
     */
    private function generateInvoiceHTML($invoice)
    {
        // Get company info
        $company = \App\Models\Company::first();
        $companyName = $company ? $company->business_name : 'Mi Empresa';
        $companyAddress = $company ? $company->address : '';
        $companyPhone = $company ? $company->phone : '';
        $companyEmail = $company ? $company->email : '';
        $companyTaxId = $company ? $company->legal_id : '';
        
        $html = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                    .company-info { margin-bottom: 20px; }
                    .invoice-info { margin-bottom: 20px; }
                    .customer-info { margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; font-weight: bold; }
                    .total { font-weight: bold; }
                    .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>FACTURA</h1>
                    <h2>{$invoice->invoice_number}</h2>
                </div>
                
                <div class='company-info'>
                    <h3>{$companyName}</h3>
                    <p>{$companyAddress}</p>
                    <p>Tel: {$companyPhone} | Email: {$companyEmail}</p>
                    <p>Cédula Jurídica: {$companyTaxId}</p>
                </div>
                
                <div class='invoice-info'>
                    <p><strong>Fecha de Factura:</strong> " . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') . "</p>
                    <p><strong>Condición de Venta:</strong> " . ($invoice->sale_condition == 'Cash' ? 'Contado' : 'Crédito (' . $invoice->credit_days . ' días)') . "</p>
                </div>
                
                <div class='customer-info'>
                    <h3>Cliente</h3>
                    <p><strong>Nombre:</strong> {$invoice->customer_name}</p>
                    <p><strong>Email:</strong> {$invoice->customer_email}</p>
                    <p><strong>Teléfono:</strong> {$invoice->customer_phone}</p>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>";
                    
        foreach ($invoice->details as $detail) {
            $html .= "
                        <tr>
                            <td>{$detail->productService->name}</td>
                            <td>{$detail->quantity}</td>
                            <td>₡" . number_format($detail->unit_price, 2) . "</td>
                            <td>₡" . number_format($detail->total, 2) . "</td>
                        </tr>";
        }
                    
        $html .= "
                    </tbody>
                </table>
                
                <div class='total'>
                    <p><strong>Subtotal:</strong> ₡" . number_format($invoice->subtotal, 2) . "</p>";
                    
        if ($invoice->total_discount > 0) {
            $html .= "<p><strong>Descuentos:</strong> ₡" . number_format($invoice->total_discount, 2) . "</p>";
        }
                    
        $html .= "
                    <p><strong>Impuesto (13%):</strong> ₡" . number_format($invoice->total_tax, 2) . "</p>
                    <p><strong>TOTAL:</strong> ₡" . number_format($invoice->grand_total, 2) . "</p>
                </div>
                
                <div class='footer'>
                    <p>Factura generada el " . now()->format('d/m/Y H:i:s') . "</p>
                    <p>{$companyName} - Sistema de Facturación</p>
                </div>
            </body>
            </html>
        ";
        
        return $html;
    }

    /**
     * Generate XML file for Costa Rica electronic invoice
     */
    private function generateInvoiceXML($invoice, $company)
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<FacturaElectronica xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.3/facturaElectronica"
                    xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
                    xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <Clave>' . $this->generateClave($invoice, $company) . '</Clave>
    <CodigoActividad>' . ($company->activity_code ?? '620100000000') . '</CodigoActividad>
    <NumeroConsecutivo>' . $invoice->invoice_number . '</NumeroConsecutivo>
    <FechaEmision>' . \Carbon\Carbon::parse($invoice->issue_date)->format('Y-m-d\TH:i:s-06:00') . '</FechaEmision>
    <Emisor>
        <Nombre>' . htmlspecialchars($company->business_name ?? 'Mi Empresa S.A.') . '</Nombre>
        <Identificacion>
            <Tipo>02</Tipo>
            <Numero>' . ($company->legal_id ?? '3-101-123456') . '</Numero>
        </Identificacion>
        <NombreComercial>' . htmlspecialchars($company->business_name ?? 'Mi Empresa S.A.') . '</NombreComercial>
        <Ubicacion>
            <Provincia>' . ($company->province ?? '01') . '</Provincia>
            <Canton>' . ($company->canton ?? '01') . '</Canton>
            <Distrito>' . ($company->district ?? '01') . '</Distrito>
            <Barrio>' . ($company->neighborhood ?? '01') . '</Barrio>
            <OtrasSenas>' . htmlspecialchars($company->address ?? 'San José, Costa Rica') . '</OtrasSenas>
        </Ubicacion>
        <Telefono>
            <CodigoPais>506</CodigoPais>
            <NumTelefono>' . ($company->phone ?? '22222222') . '</NumTelefono>
        </Telefono>
        <Fax>
            <CodigoPais>506</CodigoPais>
            <NumTelefono>' . ($company->phone ?? '22222222') . '</NumTelefono>
        </Fax>
        <CorreoElectronico>' . ($company->email ?? 'info@miempresa.com') . '</CorreoElectronico>
    </Emisor>
    <Receptor>
        <Nombre>' . htmlspecialchars($invoice->customer ? $invoice->customer->name_business_name : 'Cliente no especificado') . '</Nombre>
        <Identificacion>
            <Tipo>' . ($invoice->customer && $invoice->customer->identification_type == 'Cedula' ? '01' : '02') . '</Tipo>
            <Numero>' . ($invoice->customer ? $invoice->customer->identification_number : '000000000') . '</Numero>
        </Identificacion>
        <NombreComercial>' . htmlspecialchars($invoice->customer ? $invoice->customer->name_business_name : 'Cliente no especificado') . '</NombreComercial>
        <Ubicacion>
            <Provincia>' . ($invoice->customer ? ($invoice->customer->province ?? '01') : '01') . '</Provincia>
            <Canton>' . ($invoice->customer ? ($invoice->customer->canton ?? '01') : '01') . '</Canton>
            <Distrito>' . ($invoice->customer ? ($invoice->customer->district ?? '01') : '01') . '</Distrito>
            <Barrio>' . ($invoice->customer ? ($invoice->customer->neighborhood ?? '01') : '01') . '</Barrio>
            <OtrasSenas>' . htmlspecialchars($invoice->customer ? $invoice->customer->address : 'Dirección no especificada') . '</OtrasSenas>
        </Ubicacion>
        <Telefono>
            <CodigoPais>506</CodigoPais>
            <NumTelefono>' . ($invoice->customer ? ($invoice->customer->phone ?? '00000000') : '00000000') . '</NumTelefono>
        </Telefono>
        <Fax>
            <CodigoPais>506</CodigoPais>
            <NumTelefono>' . ($invoice->customer ? ($invoice->customer->phone ?? '00000000') : '00000000') . '</NumTelefono>
        </Fax>
        <CorreoElectronico>' . ($invoice->customer ? $invoice->customer->email : 'cliente@example.com') . '</CorreoElectronico>
    </Receptor>
    <CondicionVenta>' . ($invoice->sale_condition == 'Cash' ? '01' : '02') . '</CondicionVenta>
    <PlazoCredito>' . ($invoice->credit_days ?? 0) . '</PlazoCredito>
    <MedioPago>' . ($invoice->payment_method == 'Cash' ? '01' : ($invoice->payment_method == 'Transfer' ? '02' : '03')) . '</MedioPago>
    <DetalleServicio>';

        foreach ($invoice->details as $detail) {
            $xmlContent .= '
        <LineaDetalle>
            <NumeroLinea>' . ($detail->id ?? 1) . '</NumeroLinea>
            <Codigo>
                <Tipo>01</Tipo>
                <Codigo>' . ($detail->productService ? $detail->productService->code ?? '001' : '001') . '</Codigo>
            </Codigo>
            <Cantidad>' . number_format($detail->quantity, 3) . '</Cantidad>
            <UnidadMedida>' . ($detail->productService ? $detail->productService->unit ?? 'Unid' : 'Unid') . '</UnidadMedida>
            <Detalle>' . htmlspecialchars($detail->productService ? $detail->productService->name_description : 'Producto no especificado') . '</Detalle>
            <PrecioUnitario>' . number_format($detail->unit_price, 5) . '</PrecioUnitario>
            <MontoTotal>' . number_format($detail->item_total, 2) . '</MontoTotal>
            <SubTotal>' . number_format($detail->item_subtotal, 2) . '</SubTotal>';

            if ($detail->item_discount > 0) {
                $xmlContent .= '
            <Descuento>
                <MontoDescuento>' . number_format($detail->item_discount, 2) . '</MontoDescuento>
                <NaturalezaDescuento>Descuento por cantidad</NaturalezaDescuento>
            </Descuento>';
            }

            $xmlContent .= '
            <Impuesto>
                <Codigo>01</Codigo>
                <CodigoTarifa>08</CodigoTarifa>
                <Tarifa>13.00</Tarifa>
                <Monto>' . number_format($detail->item_tax ?? ($detail->item_total * 0.13), 2) . '</Monto>
            </Impuesto>
            <MontoTotalLinea>' . number_format($detail->item_total, 2) . '</MontoTotalLinea>
        </LineaDetalle>';
        }

        $xmlContent .= '
    </DetalleServicio>
    <ResumenFactura>
        <CodigoTipoMoneda>
            <CodigoMoneda>CRC</CodigoMoneda>
            <TipoCambio>1.00000</TipoCambio>
        </CodigoTipoMoneda>
        <TotalServGravados>' . number_format($invoice->subtotal, 2) . '</TotalServGravados>
        <TotalServExentos>0.00</TotalServExentos>
        <TotalServExonerado>0.00</TotalServExonerado>
        <TotalMercanciasGravadas>0.00</TotalMercanciasGravadas>
        <TotalMercanciasExentas>0.00</TotalMercanciasExentas>
        <TotalMercExonerada>0.00</TotalMercExonerada>
        <TotalGravado>' . number_format($invoice->subtotal, 2) . '</TotalGravado>
        <TotalExento>0.00</TotalExento>
        <TotalExonerado>0.00</TotalExonerado>
        <TotalVenta>' . number_format($invoice->subtotal, 2) . '</TotalVenta>
        <TotalDescuentos>' . number_format($invoice->total_discount, 2) . '</TotalDescuentos>
        <TotalVentaNeta>' . number_format($invoice->subtotal, 2) . '</TotalVentaNeta>
        <TotalImpuesto>' . number_format($invoice->total_tax, 2) . '</TotalImpuesto>
        <TotalIVADevuelto>0.00</TotalIVADevuelto>
        <TotalOtrosCargos>0.00</TotalOtrosCargos>
        <TotalComprobante>' . number_format($invoice->grand_total, 2) . '</TotalComprobante>
    </ResumenFactura>
    <InformacionReferencia>
        <TipoDoc>01</TipoDoc>
        <Numero>REF-' . $invoice->invoice_number . '</Numero>
        <FechaEmision>' . \Carbon\Carbon::parse($invoice->issue_date)->format('Y-m-d\TH:i:s-06:00') . '</FechaEmision>
        <Codigo>01</Codigo>
        <Razon>Factura de referencia</Razon>
    </InformacionReferencia>
    <Otros>
        <OtroTexto>Factura generada por Sistema de Facturación</OtroTexto>
    </Otros>
</FacturaElectronica>';

        // Save XML to temporary file
        $xmlPath = storage_path('app/temp/factura-' . $invoice->invoice_number . '-' . time() . '.xml');
        if (!file_exists(dirname($xmlPath))) {
            mkdir(dirname($xmlPath), 0755, true);
        }
        file_put_contents($xmlPath, $xmlContent);

        return $xmlPath;
    }

    /**
     * Generate XML response file for Costa Rica electronic invoice
     */
    private function generateResponseXML($invoice, $company)
    {
        $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<MensajeReceptor xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.3/mensajeReceptor">
    <Clave>' . $this->generateClave($invoice, $company) . '</Clave>
    <NumeroCedulaEmisor>' . ($company->legal_id ?? '3-101-123456') . '</NumeroCedulaEmisor>
    <FechaEmisionDoc>' . \Carbon\Carbon::parse($invoice->issue_date)->format('Y-m-d\TH:i:s-06:00') . '</FechaEmisionDoc>
    <Mensaje>Aceptado</Mensaje>
    <DetalleMensaje>Factura aceptada correctamente</DetalleMensaje>
    <MontoTotalImpuesto>0.00</MontoTotalImpuesto>
    <TotalFactura>' . number_format($invoice->grand_total, 2) . '</TotalFactura>
    <NumeroCedulaReceptor>' . ($invoice->customer ? $invoice->customer->identification_number : '000000000') . '</NumeroCedulaReceptor>
    <NumeroConsecutivoReceptor>' . $invoice->invoice_number . '</NumeroConsecutivoReceptor>
</MensajeReceptor>';

        // Save XML to temporary file
        $xmlPath = storage_path('app/temp/respuesta-' . $invoice->invoice_number . '-' . time() . '.xml');
        if (!file_exists(dirname($xmlPath))) {
            mkdir(dirname($xmlPath), 0755, true);
        }
        file_put_contents($xmlPath, $xmlContent);

        return $xmlPath;
    }

    /**
     * Generate clave for Costa Rica electronic invoice
     */
    private function generateClave($invoice, $company)
    {
        // Format: CR + Tipo Documento + Cédula + Situación + Año + Mes + Día + Número Consecutivo + Tipo Situación + Clave Seguridad
        $pais = '506';
        $tipoDocumento = '01'; // Factura
        $cedula = str_replace(['-', ' '], '', $company->legal_id ?? '3101123456');
        $situacion = '1'; // Normal
        $fecha = \Carbon\Carbon::parse($invoice->issue_date);
        $ano = $fecha->format('y');
        $mes = $fecha->format('m');
        $dia = $fecha->format('d');
        $consecutivo = str_pad($invoice->id, 10, '0', STR_PAD_LEFT);
        $tipoSituacion = '1'; // Normal
        $claveSeguridad = '12345678'; // 8 dígitos aleatorios

        return $pais . $tipoDocumento . $cedula . $situacion . $ano . $mes . $dia . $consecutivo . $tipoSituacion . $claveSeguridad;
    }
} 