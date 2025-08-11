<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserMovementLog;
use App\Models\UserLoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditController extends Controller
{
    /**
     * Get user movement logs with filters
     */
    public function getMovementLogs(Request $request)
    {
        try {
            $query = UserMovementLog::with(['user:id,name,username']);

            // Filter by user
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by action
            if ($request->has('action') && $request->action) {
                $query->where('action_performed', $request->action);
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            // Filter by module
            if ($request->has('module') && $request->module) {
                $query->where('module', $request->module);
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo bitácora de movimientos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user login logs with filters
     */
    public function getLoginLogs(Request $request)
    {
        try {
            $query = UserLoginLog::with(['user:id,name,username']);

            // Filter by user
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by username
            if ($request->has('username') && $request->username) {
                $query->where('username', 'like', '%' . $request->username . '%');
            }

            // Filter by event type
            if ($request->has('event_type') && $request->event_type) {
                $query->where('event_type', $request->event_type);
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo bitácora de ingresos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get users for filter dropdown
     */
    public function getUsers()
    {
        try {
            $users = User::select('id', 'name', 'username')
                        ->where('status', 'Active')
                        ->orderBy('name')
                        ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo usuarios: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get audit statistics
     */
    public function getStatistics()
    {
        try {
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();

            // Movement logs statistics
            $movementsToday = UserMovementLog::whereDate('created_at', $today)->count();
            $movementsThisMonth = UserMovementLog::where('created_at', '>=', $thisMonth)->count();

            // Login logs statistics
            $loginsToday = UserLoginLog::whereDate('created_at', $today)->count();
            $loginsThisMonth = UserLoginLog::where('created_at', '>=', $thisMonth)->count();
            $failedLoginsToday = UserLoginLog::whereDate('created_at', $today)
                                            ->where('event_type', 'Failed_Login')
                                            ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'movements' => [
                        'today' => $movementsToday,
                        'this_month' => $movementsThisMonth,
                    ],
                    'logins' => [
                        'today' => $loginsToday,
                        'this_month' => $loginsThisMonth,
                        'failed_today' => $failedLoginsToday,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo estadísticas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export audit logs
     */
    public function exportLogs(Request $request)
    {
        try {
            $type = $request->get('type', 'movements'); // movements or logins
            $filters = $request->get('filters', []);

            if ($type === 'movements') {
                $query = UserMovementLog::with(['user:id,name,username']);
            } else {
                $query = UserLoginLog::with(['user:id,name,username']);
            }

            // Apply filters
            if (isset($filters['user_id']) && $filters['user_id']) {
                $query->where('user_id', $filters['user_id']);
            }

            if (isset($filters['date_from']) && $filters['date_from']) {
                $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
            }

            if (isset($filters['date_to']) && $filters['date_to']) {
                $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
            }

            if ($type === 'movements' && isset($filters['action']) && $filters['action']) {
                $query->where('action_performed', $filters['action']);
            }

            if ($type === 'logins' && isset($filters['event_type']) && $filters['event_type']) {
                $query->where('event_type', $filters['event_type']);
            }

            $logs = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $logs,
                'filename' => 'bitacora_' . $type . '_' . date('Y-m-d_H-i-s') . '.json',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exportando bitácora: ' . $e->getMessage(),
            ], 500);
        }
    }
}
