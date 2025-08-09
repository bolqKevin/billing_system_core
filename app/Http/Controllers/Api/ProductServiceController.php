<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProductServiceController extends Controller
{
    /**
     * Display a listing of products/services
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = $user ? $user->company_id : null;
        
        $query = ProductService::query();
        
        // Filtrar por empresa del usuario
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name_description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $products = $query->orderBy('name_description')->paginate(15);

        return response()->json($products);
    }

    /**
     * Store a newly created product/service
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:products_services',
            'name_description' => 'required|string|max:200',
            'type' => 'required|in:Product,Service',
            'unit_measure' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'status' => 'in:Active,Inactive',
        ]);

        $data = $request->all();
        $data['company_id'] = Auth::user()->company_id;
        $product = ProductService::create($data);

        return response()->json([
            'message' => 'Producto/Servicio creado exitosamente',
            'data' => $product,
        ], 201);
    }

    /**
     * Display the specified product/service
     */
    public function show($id)
    {
        try {
            $productService = ProductService::findOrFail($id);
            return response()->json($productService);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto/Servicio no encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified product/service
     */
    public function update(Request $request, $id)
    {
        try {
            $productService = ProductService::findOrFail($id);
            
            // Log the incoming request
            Log::info('Updating product/service', [
                'product_id' => $productService->id,
                'request_data' => $request->all()
            ]);

            $request->validate([
                'code' => 'required|string|max:50|unique:products_services,code,' . $productService->id,
                'name_description' => 'required|string|max:200',
                'type' => 'required|in:Product,Service',
                'unit_measure' => 'required|string|max:50',
                'unit_price' => 'required|numeric|min:0',
                'tax_rate' => 'required|numeric|min:0|max:100',
                'status' => 'in:Active,Inactive',
            ], [
                'code.unique' => 'El código ya existe en otro producto/servicio.',
                'code.required' => 'El código es obligatorio.',
                'name_description.required' => 'El nombre/descripción es obligatorio.',
                'type.required' => 'El tipo es obligatorio.',
                'unit_measure.required' => 'La unidad de medida es obligatoria.',
                'unit_price.required' => 'El precio unitario es obligatorio.',
                'unit_price.numeric' => 'El precio unitario debe ser un número.',
                'tax_rate.required' => 'La tasa de impuesto es obligatoria.',
                'tax_rate.numeric' => 'La tasa de impuesto debe ser un número.',
            ]);

            $productService->update($request->all());

            Log::info('Product/Service updated successfully', [
                'product_id' => $productService->id
            ]);

            return response()->json([
                'message' => 'Producto/Servicio actualizado exitosamente',
                'data' => $productService,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Producto/Servicio no encontrado',
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
     * Remove the specified product/service
     */
    public function destroy($id)
    {
        try {
            $productService = ProductService::findOrFail($id);
            
            Log::info('Deleting product/service', [
                'product_id' => $productService->id
            ]);

            $productService->update(['status' => 'Inactive']);

            Log::info('Product/Service deleted successfully', [
                'product_id' => $productService->id
            ]);

            return response()->json([
                'message' => 'Producto/Servicio deshabilitado exitosamente',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Product/Service not found', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Producto/Servicio no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting product/service', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al deshabilitar el producto/servicio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
} 