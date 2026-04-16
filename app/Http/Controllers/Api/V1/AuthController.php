<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Register a new user account.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255', new \App\Rules\RestrictedName()],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'is_verified' => false,
        ]);

        $userRole = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => config('auth.defaults.guard', 'web')]
        );
        $user->assignRole($userRole);

        event(new Registered($user));

        // Send verification code via email
        $user->sendVerificationEmail();

        // Create Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحساب بنجاح. يرجى التحقق من بريدك الإلكتروني.',
            'data'    => [
                'user'  => $this->formatUser($user),
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Authenticate a user and return a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        // Check if user is banned
        if ($user->is_banned) {
            return response()->json([
                'success' => false,
                'message' => 'تم حظر حسابك. يرجى التواصل مع الدعم الفني.',
            ], 403);
        }

        // Check trusted device
        $needsVerification = true;
        if ($request->hasCookie('opticvault_trusted_device')) {
            $cookieToken = $request->cookie('opticvault_trusted_device');
            $hashedCookie = hash('sha256', $cookieToken);
            $verification = $user->verification;

            if ($verification
                && $verification->device_token === $hashedCookie
                && $verification->device_trusted_until
                && $verification->device_trusted_until->isFuture()
                && $verification->last_login_ip === $request->ip()
            ) {
                $needsVerification = false;
            }
        }

        // If not verified at all, send verification code
        if (!$user->is_verified) {
            $user->sendVerificationEmail();
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success'            => true,
                'requires_verification' => true,
                'message'            => 'يجب تأكيد بريدك الإلكتروني أولاً.',
                'data'               => [
                    'user'  => $this->formatUser($user),
                    'token' => $token,
                ],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data'    => [
                'user'  => $this->formatUser($user),
                'token' => $token,
                'needs_2fa' => $needsVerification,
            ],
        ]);
    }

    /**
     * Logout and revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * Verify a 6-digit email verification code.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'code'         => 'required|digits:6',
            'trust_device' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // Check code expiration (3 minutes)
        $verification = $user->verification;
        if ($verification && $verification->updated_at->addMinutes(3)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'انتهت صلاحية هذا الكود (3 دقائق). يرجى طلب كود جديد.',
            ], 422);
        }

        if ($request->code !== $user->verification_code) {
            return response()->json([
                'success' => false,
                'message' => 'الرمز غير صحيح. يرجى المحاولة مرة أخرى.',
            ], 422);
        }

        $user->update([
            'is_verified'       => true,
            'verification_code' => null,
        ]);

        $response = [
            'success' => true,
            'message' => 'تم تأكيد الحساب بنجاح.',
        ];

        // Handle trusted device
        if ($request->boolean('trust_device')) {
            $rawToken    = Str::random(64);
            $hashedToken = hash('sha256', $rawToken);

            $user->verification()->update([
                'device_token'         => $hashedToken,
                'device_trusted_until' => now()->addDays(30),
                'last_login_ip'        => $request->ip(),
            ]);

            $response['trusted_device_token'] = $rawToken;
        }

        return response()->json($response);
    }

    /**
     * Resend verification code.
     */
    public function resendVerificationCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'حسابك مُفعّل بالفعل.',
            ], 400);
        }

        $user->sendVerificationEmail();

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة إرسال رمز التحقق بنجاح.',
        ]);
    }

    /**
     * Send a password reset code to the user's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        $code = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $code, 'created_at' => now()]
        );

        try {
            \Illuminate\Support\Facades\Mail::to($request->email)
                ->queue(new \App\Mail\ResetPasswordCode($code));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send reset code: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز إعادة التعيين إلى بريدك الإلكتروني.',
        ]);
    }

    /**
     * Verify the password reset code.
     */
    public function verifyResetCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|numeric',
        ]);

        $resetData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->first();

        if (!$resetData) {
            return response()->json([
                'success' => false,
                'message' => 'الرمز غير صحيح أو منتهي الصلاحية.',
            ], 422);
        }

        return response()->json([
            'success'     => true,
            'message'     => 'تم التحقق من الرمز بنجاح.',
            'reset_token' => encrypt($request->email . '|' . $request->code),
        ]);
    }

    /**
     * Reset the password using the verified token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'reset_token' => 'required|string',
            'password'    => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $decrypted = decrypt($request->reset_token);
            [$email, $code] = explode('|', $decrypted);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'رمز إعادة التعيين غير صالح.',
            ], 422);
        }

        $resetData = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $code)
            ->first();

        if (!$resetData) {
            return response()->json([
                'success' => false,
                'message' => 'رمز إعادة التعيين منتهي الصلاحية.',
            ], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود.'], 404);
        }

        $user->forceFill([
            'password'       => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة تعيين كلمة المرور بنجاح.',
        ]);
    }

    /**
     * Get the authenticated user's full profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'roles', 'verification']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    /**
     * Format user data for API responses.
     */
    private function formatUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'avatar'            => $user->avatar,
            'is_verified'       => (bool) $user->is_verified,
            'is_badge_verified' => (bool) $user->is_badge_verified,
            'role'              => $user->role,
            'roles'             => $user->roles->pluck('name'),
            'storage_used'      => $user->storage_used_bytes,
            'storage_limit'     => $user->storage_limit_bytes,
            'is_public_profile' => (bool) $user->is_public_profile,
            'created_at'        => $user->created_at,
        ];
    }
}
