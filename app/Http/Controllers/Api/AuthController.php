<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        Log::info('Login attempt', [
            'username' => $request->username,
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
                'username' => 'required|string',
                'password' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            Log::error('Login validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'validation_rules' => [
                    'username' => 'required|string',
                    'password' => 'required|string',
                ]
            ]);
            throw $e;
        }

        $user = User::with(['role.permissions'])->where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            UserLoginLog::create([
                'user_id' => $user ? $user->id : null,
                'username' => $request->username,
                'event_type' => 'Failed_Login',
            ]);

            Log::warning('Login failed - invalid credentials', [
                'username' => $request->username,
                'user_exists' => $user ? true : false
            ]);
            throw ValidationException::withMessages([
                'username' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if ($user->status !== 'Active') {
            UserLoginLog::create([
                'user_id' => $user->id,
                'username' => $request->username,
                'event_type' => 'Failed_Login',
            ]);

            Log::warning('Login failed - inactive user', [
                'username' => $request->username,
                'status' => $user->status
            ]);
            throw ValidationException::withMessages([
                'username' => ['Su cuenta está inactiva. Contacte al administrador.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        UserLoginLog::create([
            'user_id' => $user->id,
            'username' => $user->username,
            'event_type' => 'Successful_Login',
        ]);

        Log::info('Login successful', [
            'user_id' => $user->id,
            'username' => $user->username,
            'role' => $user->role ? $user->role->name : 'No role'
        ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status,
                'company_id' => $user->company_id,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'description' => $user->role->description,
                    'status' => $user->role->status,
                    'permissions' => $user->role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'description' => $permission->description,
                            'module' => $permission->module,
                        ];
                    })
                ] : null,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Logout user (Revoke the token)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Log logout event
        UserLoginLog::create([
            'user_id' => $user->id,
            'username' => $user->username,
            'event_type' => 'Logout',
        ]);

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        $user = $request->user()->load(['role.permissions']);
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'company_id' => $user->company_id,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'description' => $user->role->description,
                'status' => $user->role->status,
                'permissions' => $user->role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'description' => $permission->description,
                        'module' => $permission->module,
                    ];
                })
            ] : null,
        ]);
    }

    /**
     * Send password reset OTP
     */
    public function sendPasswordResetOtp(Request $request)
    {
        // Configure mail settings from database
        $smtpSettings = \App\Models\Setting::where('company_id', 1)
            ->whereIn('code', [
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
                'smtp_encryption', 'smtp_from_email', 'smtp_from_name'
            ])
            ->pluck('value', 'code')
            ->toArray();

        if (!empty($smtpSettings['smtp_host'])) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.transport' => 'smtp',
                'mail.mailers.smtp.host' => $smtpSettings['smtp_host'],
                'mail.mailers.smtp.port' => $smtpSettings['smtp_port'],
                'mail.mailers.smtp.username' => $smtpSettings['smtp_username'],
                'mail.mailers.smtp.password' => $smtpSettings['smtp_password'],
                'mail.mailers.smtp.encryption' => $smtpSettings['smtp_encryption'],
                'mail.from.address' => $smtpSettings['smtp_from_email'],
                'mail.from.name' => $smtpSettings['smtp_from_name'],
            ]);
        }
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Si el correo electrónico existe en nuestro sistema, recibirás un código de verificación.',
            ], 200); // Always return 200 for security reasons
        }

        if ($user->status !== 'Active') {
            return response()->json([
                'message' => 'Su cuenta está inactiva. Contacte al administrador.',
            ], 400);
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Str::random(60),
                'otp' => $otp,
                'otp_expires_at' => now()->addMinutes(10), // OTP expires in 10 minutes
                'otp_attempts' => 0,
                'created_at' => now(),
            ]
        );

        // Send email with OTP
        try {
            Mail::raw("Su código de verificación para restablecer la contraseña es: {$otp}\n\nEste código expira en 10 minutos.\n\nSi no solicitó este cambio, ignore este mensaje.", function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Código de Verificación - Restablecer Contraseña');
            });

            Log::info('Password reset OTP sent', [
                'email' => $request->email,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Si el correo electrónico existe en nuestro sistema, recibirás un código de verificación.',
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending password reset OTP', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al enviar el código de verificación. Intente nuevamente.',
            ], 500);
        }
    }

    /**
     * Verify OTP only (without resetting password)
     */
    public function verifyOtpOnly(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$resetToken) {
            return response()->json([
                'message' => 'Código de verificación inválido.',
            ], 400);
        }

        // Check if OTP is expired
        if (now()->isAfter($resetToken->otp_expires_at)) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'El código de verificación ha expirado. Solicite uno nuevo.',
            ], 400);
        }

        // Check if too many attempts
        if ($resetToken->otp_attempts >= 3) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Demasiados intentos fallidos. Solicite un nuevo código.',
            ], 400);
        }

        Log::info('OTP verified successfully', [
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Código verificado correctamente.',
        ]);
    }

    /**
     * Verify OTP and reset password
     */
    public function verifyOtpAndResetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$resetToken) {
            return response()->json([
                'message' => 'Código de verificación inválido.',
            ], 400);
        }

        // Check if OTP is expired
        if (now()->isAfter($resetToken->otp_expires_at)) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'El código de verificación ha expirado. Solicite uno nuevo.',
            ], 400);
        }

        // Check if too many attempts
        if ($resetToken->otp_attempts >= 3) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Demasiados intentos fallidos. Solicite un nuevo código.',
            ], 400);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        Log::info('Password reset successful', [
            'email' => $request->email,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Contraseña restablecida exitosamente.',
        ]);
    }

    /**
     * Increment OTP attempts (for failed verification)
     */
    public function incrementOtpAttempts(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->increment('otp_attempts');

        return response()->json([
            'message' => 'Intento registrado.',
        ]);
    }
} 