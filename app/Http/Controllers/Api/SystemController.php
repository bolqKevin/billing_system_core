<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Setting;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemController extends Controller
{
    /**
     * Get system information
     */
    public function info()
    {
        try {
            $systemInfo = [
                'system_name' => 'FactuGriego',
                'version' => '1.0.0',
                'release_date' => '2025-08-16',
                'owner' => 'Construcciones Griegas B&B S.A.',
                'developer' => 'Sistema de Facturación',
                'technologies' => 'Laravel, PHP, MySQL, Vue.js',
            ];

            return response()->json([
                'success' => true,
                'data' => $systemInfo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting system info: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function health()
    {
        return response()->json([
            'success' => true,
            'message' => 'Sistema funcionando correctamente',
            'data' => [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Get system settings
     */
    public function getSettings()
    {
        $user = Auth::user();
        $companyId = $user ? $user->company_id : 1;
        
        $settings = Setting::where('company_id', $companyId)
            ->pluck('value', 'code')
            ->toArray();

        return response()->json($settings);
    }

    /**
     * Update system settings
     */
    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        $companyId = $user ? $user->company_id : 1;

        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $code => $value) {
            Setting::updateOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                ['value' => $value]
            );
        }

        return response()->json([
            'message' => 'Configuración actualizada exitosamente',
        ]);
    }

    /**
     * Get company information
     */
    public function getCompanyInfo()
    {
        $user = Auth::user();
        $company = $user ? $user->company : Company::first();

        return response()->json([
            'data' => $company,
        ]);
    }

    /**
     * Update company information
     */
    public function updateCompanyInfo(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        $company = $user->company;
        
        if (!$company) {
            return response()->json([
                'message' => 'Compañía no encontrada',
            ], 404);
        }

        $request->validate([
            'company_name' => 'required|string|max:200',
            'business_name' => 'required|string|max:200',
            'legal_id' => 'required|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'required|string',
            'invoice_prefix' => 'required|string|max:10',
            'invoice_current_consecutive' => 'required|integer|min:1',
        ]);

        try {
            $company->update([
                'company_name' => $request->company_name,
                'business_name' => $request->business_name,
                'legal_id' => $request->legal_id,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'invoice_prefix' => $request->invoice_prefix,
                'invoice_current_consecutive' => $request->invoice_current_consecutive,
            ]);

            return response()->json([
                'message' => 'Información de la empresa actualizada exitosamente',
                'data' => $company,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la información de la empresa: ' . $e->getMessage(),
            ], 500);
        }
    }
} 