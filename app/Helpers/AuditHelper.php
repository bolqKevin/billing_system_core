<?php

namespace App\Helpers;

use App\Models\UserMovementLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuditHelper
{
    /**
     * Registrar un movimiento de usuario
     */
    public static function logMovement($action, $module, $affectedRecordId = null, $details = null)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return false;
            }

            UserMovementLog::create([
                'user_id' => $user->id,
                'action_performed' => $action,
                'affected_record_id' => $affectedRecordId,
                'module' => $module,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error logging movement: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar creación de registro
     */
    public static function logCreate($module, $recordId = null, $details = null)
    {
        return self::logMovement('Create', $module, $recordId, $details);
    }

    /**
     * Registrar actualización de registro
     */
    public static function logUpdate($module, $recordId = null, $details = null)
    {
        return self::logMovement('Update', $module, $recordId, $details);
    }

    /**
     * Registrar eliminación de registro
     */
    public static function logDelete($module, $recordId = null, $details = null)
    {
        return self::logMovement('Delete', $module, $recordId, $details);
    }

    /**
     * Registrar visualización de registro
     */
    public static function logView($module, $recordId = null, $details = null)
    {
        return self::logMovement('View', $module, $recordId, $details);
    }

    /**
     * Obtener el ID del módulo basado en la ruta
     */
    public static function getModuleFromRoute($route)
    {
        $moduleMap = [
            'customers' => ['customers', 'clientes'],
            'products' => ['products', 'productos', 'services', 'servicios'],
            'invoices' => ['invoices', 'facturas', 'bills'],
            'users' => ['users', 'usuarios'],
            'settings' => ['settings', 'configuracion', 'configuration'],
            'roles' => ['roles', 'roles'],
            'permissions' => ['permissions', 'permisos'],
        ];

        $route = strtolower($route);

        foreach ($moduleMap as $module => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($route, $keyword) !== false) {
                    return $module;
                }
            }
        }

        return 'general';
    }

    /**
     * Obtener la acción basada en el método HTTP
     */
    public static function getActionFromMethod($method)
    {
        $actionMap = [
            'GET' => 'View',
            'POST' => 'Create',
            'PUT' => 'Update',
            'PATCH' => 'Update',
            'DELETE' => 'Delete',
        ];

        return $actionMap[$method] ?? 'View';
    }
} 