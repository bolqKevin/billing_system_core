<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        // Log the incoming request data
        Log::info('Login attempt', [
            'email' => $request->email,
            'has_password' => $request->has('password'),
            'password_length' => $request->password ? strlen($request->password) : 0,
            'headers' => $request->headers->all(),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept'),
            'all_data' => $request->all(),
            'method' => $request->method(),
            'url' => $request->url()
        ]);

        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            Log::error('Login validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'validation_rules' => [
                    'email' => 'required|email',
                    'password' => 'required|string',
                ]
            ]);
            throw $e;
        }

        $user = User::with('role')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('Login failed - invalid credentials', [
                'email' => $request->email,
                'user_exists' => $user ? true : false
            ]);
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        Log::info('Login successful', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role ? $user->role->name : null,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Logout user (Revoke the token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        $user = $request->user()->load('role');
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role ? $user->role->name : null,
        ]);
    }
} 