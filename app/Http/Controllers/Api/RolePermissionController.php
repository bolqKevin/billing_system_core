<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Helpers\AuditHelper;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of roles
     */
    public function index(Request $request)
    {
        $query = Role::with(['permissions']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            if (!$request->has('show_inactive') || $request->show_inactive !== 'true') {
                $query->where('status', 'Active');
            }
        }

        $roles = $query->orderBy('name')->paginate(15);

        return response()->json($roles);
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique' => 'El nombre del rol ya existe.',
            'name.max' => 'El nombre del rol no puede exceder 50 caracteres.',
            'status.required' => 'El estado del rol es obligatorio.',
            'status.in' => 'El estado debe ser Active o Inactive.',
            'permissions.array' => 'Los permisos deben ser una lista.',
            'permissions.*.exists' => 'Uno o más permisos seleccionados no existen.',
        ]);

        try {
            $role = Role::create([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            if ($request->has('permissions') && is_array($request->permissions)) {
                $role->permissions()->attach($request->permissions);
            }

            $role->load(['permissions']);

            AuditHelper::logCreate('roles', $role->id);

            Log::info('Role created successfully', [
                'role_id' => $role->id,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Rol creado exitosamente',
                'role' => $role
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating role', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error al crear el rol: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified role
     */
    public function show(Role $role)
    {
        $role->load(['permissions']);
        return response()->json($role);
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, $roleId)
    {
        // Resolver el rol manualmente para evitar problemas de Route Model Binding
        $role = Role::find($roleId);
        
        if (!$role) {
            return response()->json([
                'message' => 'El rol no fue encontrado'
            ], 404);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('roles')->ignore($role->id)
            ],
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.unique' => 'El nombre del rol ya existe.',
            'name.max' => 'El nombre del rol no puede exceder 50 caracteres.',
            'status.required' => 'El estado del rol es obligatorio.',
            'status.in' => 'El estado debe ser Active o Inactive.',
            'permissions.array' => 'Los permisos deben ser una lista.',
            'permissions.*.exists' => 'Uno o más permisos seleccionados no existen.',
        ]);

        try {
            $oldData = $role->toArray();
            
            $role->update([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
            ]);

            // Sync permissions
            if ($request->has('permissions')) {
                $role->permissions()->sync($request->permissions);
            }

            $role->load(['permissions']);

            // Log the update
            AuditHelper::logUpdate('roles', $role->id, $oldData, $role->toArray());

            Log::info('Role updated successfully', [
                'role_id' => $roleId,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Rol actualizado exitosamente',
                'role' => $role
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating role', [
                'error' => $e->getMessage(),
                'role_id' => $roleId,
                'data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar el rol: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove/Disable the specified role
     */
    public function destroy($roleId)
    {
        try {
            // Resolver el rol manualmente para evitar problemas de Route Model Binding
            $role = Role::find($roleId);
            
            if (!$role) {
                Log::error('Role not found', [
                    'role_id' => $roleId,
                    'request_id' => request()->route('role')
                ]);
                return response()->json([
                    'message' => 'El rol no fue encontrado',
                    'action' => 'error'
                ], 404);
            }

            Log::info('Starting role disable/deletion process', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'current_status' => $role->status,
            ]);

            $userCount = $role->users()->count();
            
            Log::info('User count for role', [
                'role_id' => $role->id,
                'user_count' => $userCount,
            ]);
            
            // Verificar si el rol tiene usuarios asignados
            if ($userCount > 0) {
                Log::info('Role deletion blocked due to assigned users', [
                    'role_id' => $role->id,
                    'user_count' => $userCount,
                ]);

                return response()->json([
                    'message' => "No se puede eliminar/deshabilitar el rol '{$role->name}' porque tiene {$userCount} usuario(s) asignado(s). Primero reasigne todos los usuarios a otro rol antes de proceder.",
                    'action' => 'blocked',
                    'user_count' => $userCount
                ], 400);
            }

            // Si el rol está activo, primero deshabilitarlo
            if ($role->status === 'Active') {
                Log::info('Disabling active role', [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                ]);

                $role->update(['status' => 'Inactive']);
                
                // Log the status change
                AuditHelper::logUpdate('roles', $role->id, ['status' => 'Active'], ['status' => 'Inactive']);

                return response()->json([
                    'message' => "El rol '{$role->name}' ha sido deshabilitado. Puede eliminarlo permanentemente en la próxima operación.",
                    'action' => 'disabled',
                    'role' => $role->fresh()
                ]);
            }

            // Si el rol ya está inactivo, proceder con la eliminación permanente
            Log::info('Proceeding with permanent role deletion', [
                'role_id' => $role->id,
                'role_name' => $role->name,
            ]);
            
            // Log before deletion
            AuditHelper::logDelete('roles', $role->id);

            // Intentar eliminar el rol
            $deleted = $role->delete();
            
            Log::info('Role deletion attempt result', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'deleted' => $deleted,
                'deleted_by' => Auth::id(),
            ]);

            if (!$deleted) {
                Log::error('Failed to delete role', ['role_id' => $role->id]);
                return response()->json([
                    'message' => 'Error al eliminar el rol',
                    'action' => 'error'
                ], 500);
            }

            return response()->json([
                'message' => 'Rol eliminado permanentemente',
                'action' => 'deleted'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting role', [
                'error' => $e->getMessage(),
                'role_id' => $roleId,
            ]);

            return response()->json([
                'message' => 'Error al eliminar el rol: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all permissions
     */
    public function getPermissions(Request $request)
    {
        $query = Permission::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%");
            });
        }

        // Filter by module
        if ($request->has('module')) {
            $query->where('module', $request->module);
        }

        $permissions = $query->orderBy('module')->orderBy('name')->get();

        return response()->json($permissions);
    }

    /**
     * Get permissions grouped by module
     */
    public function getPermissionsByModule()
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get();
        
        $groupedPermissions = $permissions->groupBy('module')->map(function ($modulePermissions) {
            return $modulePermissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'description' => $permission->description,
                    'module' => $permission->module,
                ];
            });
        });

        return response()->json($groupedPermissions);
    }

    /**
     * Get role statistics
     */
    public function getStats()
    {
        $stats = [
            'total' => Role::count(),
            'active' => Role::where('status', 'Active')->count(),
            'inactive' => Role::where('status', 'Inactive')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Check if role name exists
     */
    public function checkNameExists(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'exclude_id' => 'nullable|integer'
        ]);

        $query = Role::where('name', $request->name);
        
        if ($request->has('exclude_id')) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El nombre del rol ya existe' : 'El nombre del rol está disponible'
        ]);
    }
}
