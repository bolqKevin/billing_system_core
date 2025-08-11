<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        $users = $query->orderBy('name')->paginate(15);

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
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:Active,Inactive',
            'company_id' => 'nullable|exists:companies,id',
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
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required|in:Active,Inactive',
            'company_id' => 'nullable|exists:companies,id',
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
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        // No permitir eliminar el usuario actual
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'No puede eliminar su propio usuario',
            ], 400);
        }

        try {
            $user->delete();

            // Log the deletion
            AuditHelper::logDelete('users', $user->id);

            Log::info('User deleted successfully', [
                'user_id' => $user->id,
                'deleted_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Usuario eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Error al eliminar el usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, User $user)
    {
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
}
