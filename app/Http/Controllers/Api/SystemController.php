<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemInfo;
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
            $systemInfo = SystemInfo::first();

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
} 