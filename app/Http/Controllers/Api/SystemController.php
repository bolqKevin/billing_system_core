<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemInfo;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    /**
     * Get system information
     */
    public function info()
    {
        $systemInfo = SystemInfo::first();

        return response()->json([
            'success' => true,
            'data' => $systemInfo,
        ]);
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
} 