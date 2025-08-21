<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\AuditHelper;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = $user ? $user->company_id : null;
        
        $query = User::with(['role', 'company']);
        
        // Filtrar por empresa del usuario si no es super admin
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status - by default show only active users
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Solo filtrar por activos si no se está mostrando inactivos explícitamente
            if (!$request->has('show_inactive') || $request->show_inactive !== 'true') {
                $query->where('status', 'Active');
            }
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        $users = $query->orderBy('name')->paginate(15);

        // Log para debug
        Log::info('Users query result', [
            'total_users' => $users->total(),
            'first_user_status' => $users->first() ? $users->first()->status : 'no users',
            'sample_users' => $users->take(3)->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'status' => $user->status
                ];
            })
        ]);

        return response()->json($users);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'confirmPassword' => 'required|string|same:password',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:Active,Inactive',
            'company_id' => 'nullable|exists:companies,id',
        ], [
            'email.unique' => 'El correo electrónico ya está registrado en el sistema.',
            'username.unique' => 'El nombre de usuario ya está en uso.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'confirmPassword.required' => 'La confirmación de contraseña es obligatoria.',
            'confirmPassword.same' => 'Las contraseñas no coinciden.',
            'role_id.required' => 'Debe seleccionar un rol para el usuario.',
            'role_id.exists' => 'El rol seleccionado no existe.',
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
                'status' => $request->status,
                'company_id' => $request->company_id ?? Auth::user()->company_id,
            ]);

            $user->load(['role', 'company']);

            // Log the creation
            AuditHelper::logCreate('users', $user->id);

            Log::info('User created successfully', [
                'user_id' => $user->id,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'user' => $user,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error al crear el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(User $user)
    {
        $user->load(['role', 'company']);
        
        return response()->json($user);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, $userId)
    {
        // Buscar el usuario manualmente
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // Log the incoming request for debugging
        Log::info('Updating user - Request data', [
            'user_id' => $user->id,
            'current_username' => $user->username,
            'current_email' => $user->email,
            'new_username' => $request->username,
            'new_email' => $request->email,
            'request_data' => $request->all()
        ]);

        $request->validate([
            'name' => 'required|string|max:100',
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:Active,Inactive',
            'company_id' => 'nullable|exists:companies,id',
        ], [
            'email.unique' => 'El correo electrónico ya está registrado en el sistema.',
            'username.unique' => 'El nombre de usuario ya está en uso.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'role_id.required' => 'Debe seleccionar un rol para el usuario.',
            'role_id.exists' => 'El rol seleccionado no existe.',
        ]);

        try {
            $updateData = [
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'role_id' => $request->role_id,
                'status' => $request->status,
                'company_id' => $request->company_id ?? $user->company_id,
            ];

            // Solo actualizar contraseña si se proporciona
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);
            $user->load(['role', 'company']);

            // Log the update
            AuditHelper::logUpdate('users', $user->id);

            Log::info('User updated successfully', [
                'user_id' => $user->id,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user' => $user,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error updating user', [
                'user_id' => $user->id,
                'validation_errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable the specified user (soft delete)
     */
    public function destroy($userId)
    {
        // Buscar el usuario manualmente
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        Log::info('Destroy method called', [
            'user_id' => $user->id,
            'auth_user_id' => Auth::id(),
            'user_status' => $user->status
        ]);

        // No permitir deshabilitar el usuario actual
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'No puede deshabilitar su propio usuario',
            ], 400);
        }

        try {
            Log::info('Disabling user', [
                'user_id' => $user->id,
                'current_status' => $user->status
            ]);

            // Usar una consulta directa para asegurar que se actualice
            $result = DB::table('users')->where('id', $user->id)->update(['status' => 'Inactive']);
            
            Log::info('Update result', [
                'user_id' => $user->id,
                'update_result' => $result,
                'new_status' => DB::table('users')->where('id', $user->id)->value('status')
            ]);

            // Log the disable action
            AuditHelper::logMovement('Disable', 'users', $user->id);

            Log::info('User disabled successfully', [
                'user_id' => $user->id,
                'disabled_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Usuario deshabilitado exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error disabling user', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Error al deshabilitar el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently delete the specified user from database
     */
    public function permanentDelete(User $user)
    {
        // No permitir eliminar el usuario actual
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'No puede eliminar su propio usuario',
            ], 400);
        }

        try {
            Log::info('Permanently deleting user', [
                'user_id' => $user->id
            ]);

            // Check if user has associated records (invoices, etc.)
            // You can add more checks here based on your business logic
            if ($user->invoices()->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar el usuario porque tiene facturas asociadas',
                ], 400);
            }

            $userId = $user->id;
            $user->delete();

            // Log the permanent deletion
            AuditHelper::logMovement('Permanent Delete', 'users', $userId);

            Log::info('User permanently deleted successfully', [
                'user_id' => $userId,
                'deleted_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Usuario eliminado permanentemente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error permanently deleting user', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Error al eliminar permanentemente el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, $userId)
    {
        // Buscar el usuario manualmente
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $request->validate([
            'password' => 'required|string|min:8',
        ]);

        try {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            Log::info('User password reset successfully', [
                'user_id' => $user->id,
                'reset_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Contraseña actualizada exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error resetting user password', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Error al actualizar la contraseña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available roles for user creation/editing
     */
    public function getRoles()
    {
        $roles = Role::active()->orderBy('name')->get();
        
        return response()->json($roles);
    }

    /**
     * Get user statistics
     */
    public function getStats()
    {
        $user = Auth::user();
        $companyId = $user ? $user->company_id : null;
        
        $query = User::query();
        
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $stats = [
            'total' => $query->count(),
            'active' => $query->where('status', 'Active')->count(),
            'inactive' => $query->where('status', 'Inactive')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Check if email exists
     */
    public function checkEmailExists(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'exclude_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $query = User::where('email', $request->email);
        
        // Excluir al usuario actual si se está editando
        if ($request->has('exclude_user_id') && $request->exclude_user_id) {
            $query->where('id', '!=', $request->exclude_user_id);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El correo electrónico ya está registrado.' : 'El correo electrónico está disponible.'
        ]);
    }

    /**
     * Check if username exists
     */
    public function checkUsernameExists(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'exclude_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $query = User::where('username', $request->username);
        
        // Excluir al usuario actual si se está editando
        if ($request->has('exclude_user_id') && $request->exclude_user_id) {
            $query->where('id', '!=', $request->exclude_user_id);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El nombre de usuario ya está en uso.' : 'El nombre de usuario está disponible.'
        ]);
    }
}
