<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Helpers\AuditHelper;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = $user ? $user->company_id : null;
        
        $query = Customer::query();
        
        // Filtrar por empresa del usuario
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name_business_name', 'like', "%{$search}%")
                  ->orWhere('identification_number', 'like', "%{$search}%")
                  ->orWhere('commercial_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // By default, only show active customers unless specifically requested
            $query->where('status', 'Active');
        }

        // Filter by identification type
        if ($request->has('identification_type')) {
            $query->where('identification_type', $request->identification_type);
        }

        $customers = $query->orderBy('name_business_name')->paginate(15);

        return response()->json($customers);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name_business_name' => 'required|string|max:200',
                'identification_type' => 'required|in:Individual,Business',
                'identification_number' => 'required|string|max:20|unique:customers',
                'commercial_name' => 'nullable|string|max:200',
                'phone1' => 'nullable|string|max:20',
                'phone2' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'province' => 'nullable|string|max:50',
                'canton' => 'nullable|string|max:50',
                'exact_address' => 'nullable|string',
                'status' => 'in:Active,Inactive',
            ], [
                'identification_number.unique' => 'El número de identificación ya existe en otro cliente.',
                'name_business_name.required' => 'El nombre/razón social es obligatorio.',
                'identification_type.required' => 'El tipo de identificación es obligatorio.',
                'identification_number.required' => 'El número de identificación es obligatorio.',
            ]);

            $data = $request->all();
            $data['company_id'] = Auth::user()->company_id;
            $customer = Customer::create($data);

            // Log the creation
            AuditHelper::logCreate('customers', $customer->id);

            return response()->json([
                'message' => 'Cliente creado exitosamente',
                'data' => $customer,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified customer
     */
    public function show($id)
    {
        try {
            $customer = Customer::findOrFail($id);
            return response()->json($customer);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, $id)
    {
        try {
            $customer = Customer::findOrFail($id);
            
            // Log the incoming request
            Log::info('Updating customer', [
                'customer_id' => $customer->id,
                'request_data' => $request->all()
            ]);

            $request->validate([
                'name_business_name' => 'required|string|max:200',
                'identification_type' => 'required|in:Individual,Business',
                'identification_number' => 'required|string|max:20|unique:customers,identification_number,' . $customer->id . ',id',
                'commercial_name' => 'nullable|string|max:200',
                'phone1' => 'nullable|string|max:20',
                'phone2' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:100',
                'province' => 'nullable|string|max:50',
                'canton' => 'nullable|string|max:50',
                'exact_address' => 'nullable|string',
                'status' => 'in:Active,Inactive',
            ], [
                'identification_number.unique' => 'El número de identificación ya existe en otro cliente.',
                'name_business_name.required' => 'El nombre/razón social es obligatorio.',
                'identification_type.required' => 'El tipo de identificación es obligatorio.',
                'identification_number.required' => 'El número de identificación es obligatorio.',
            ]);

            $customer->update($request->all());

            // Log the update
            AuditHelper::logUpdate('customers', $customer->id);

            Log::info('Customer updated successfully', [
                'customer_id' => $customer->id
            ]);

            return response()->json([
                'message' => 'Cliente actualizado exitosamente',
                'data' => $customer,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable the specified customer (soft delete)
     */
    public function destroy($id)
    {
        try {
            $customer = Customer::findOrFail($id);
            
            Log::info('Disabling customer', [
                'customer_id' => $customer->id
            ]);

            $customer->update(['status' => 'Inactive']);

            // Log the disable action
            AuditHelper::logMovement('Disable', 'customers', $customer->id);

            Log::info('Customer disabled successfully', [
                'customer_id' => $customer->id
            ]);

            return response()->json([
                'message' => 'Cliente deshabilitado exitosamente',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Customer not found', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error disabling customer', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al deshabilitar el cliente',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently delete the specified customer from database
     */
    public function permanentDelete($id)
    {
        try {
            $customer = Customer::findOrFail($id);
            
            Log::info('Permanently deleting customer', [
                'customer_id' => $customer->id
            ]);

            // Check if customer has associated invoices
            if ($customer->invoices()->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar el cliente porque tiene facturas asociadas',
                ], 400);
            }

            $customerId = $customer->id;
            $customer->delete();

            // Log the permanent deletion
            AuditHelper::logMovement('Permanent Delete', 'customers', $customerId);

            Log::info('Customer permanently deleted successfully', [
                'customer_id' => $customerId
            ]);

            return response()->json([
                'message' => 'Cliente eliminado permanentemente',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Customer not found', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error permanently deleting customer', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al eliminar permanentemente el cliente',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
} 