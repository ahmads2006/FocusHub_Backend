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
            'name' => ['required', 'string', 'max:255', new \App\Rules\RestrictedName()],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_verified' => false,
        ]);

        $userRole = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => config('auth.defaults.guard', 'web')]
        );
        $user->assignRole($userRole);

        event(new Registered($user));

        // Send verification code via email
        $user->sendVerificationEmail();

        // Send Welcome Email
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->queue(new \App\Mail\WelcomeMail($user->name));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Welcome Email failed for user {$user->email}: " . $e->getMessage());
        }

        // Create Sanctum token
        $token = $user->createToken('api-token', ['2fa-unverified'])->plainTextToken;

        return response()->json([
            'success' => true,
            'requires_verification' => true,
            'message' => __('messages.account_created'),
            'data' => [
                'user' => $this->formatUser($user),
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
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        // Check if user is banned
        if ($user->is_banned) {
            return response()->json([
                'success' => false,
                'message' => __('messages.account_banned'),
            ], 403);
        }

        // Check trusted device
        $needsVerification = true;
        if ($request->hasCookie('opticvault_trusted_device')) {
            $cookieToken = $request->cookie('opticvault_trusted_device');
            $hashedCookie = hash('sha256', $cookieToken);
            $verification = $user->verification;

            if (
                $verification
                && $verification->device_token === $hashedCookie
                && $verification->device_trusted_until
                && $verification->device_trusted_until->isFuture()
            ) {
                $needsVerification = false;
            }
        }

        // If not verified at all or needs 2FA, send verification code
        if (!$user->is_verified || $needsVerification) {
            $user->sendVerificationEmail();
            $expiresAt = $request->boolean('remember') ? now()->addDays(30) : now()->addHours(24);
            $token = $user->createToken('api-token', ['2fa-unverified'], $expiresAt)->plainTextToken;

            return response()->json([
                'success' => true,
                'requires_verification' => true,
                'needs_2fa' => true,
                'message' => !$user->is_verified ? __('messages.email_unverified') : __('messages.2fa_required'),
                'data' => [
                    'user' => array_merge($this->formatUser($user), ['is_verified' => false]),
                    'token' => $token,
                ],
            ]);
        }

        $expiresAt = $request->boolean('remember') ? now()->addDays(30) : now()->addHours(24);
        $token = $user->createToken('api-token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('messages.login_success'),
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
                'needs_2fa' => false,
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
            'message' => __('messages.logout_success'),
        ]);
    }

    /**
     * Verify a 6-digit email verification code.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|digits:6',
            'trust_device' => 'nullable|boolean',
        ]);

        $user = $request->user();

        // Check code expiration (3 minutes)
        $verification = $user->verification;
        if ($verification && $verification->updated_at->addMinutes(3)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.code_expired'),
            ], 422);
        }

    if (!$verification || $request->code !== $verification->verification_code) {
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_code'),
            ], 422);
        }

        $user->is_verified = true;
        $user->verification_code = null;
        $user->save();

        // Update token abilities to verified status
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->abilities = ['*'];
            $token->save();
        }

        $response = [
            'success' => true,
            'message' => __('messages.account_verified'),
        ];

        // Handle trusted device
        $cookie = null;
        if ($request->boolean('trust_device')) {
            $rawToken = \Illuminate\Support\Str::random(64);
            $hashedToken = hash('sha256', $rawToken);

            $user->verification()->update([
                'device_token' => $hashedToken,
                'device_trusted_until' => now()->addDays(30),
                'last_login_ip' => $request->ip(),
            ]);

            // Set cookie for 30 days
            $cookie = cookie('opticvault_trusted_device', $rawToken, 30 * 24 * 60, null, null, true, true, false, 'None');
        }

        $jsonResponse = response()->json($response);
        if ($cookie) {
            $jsonResponse->withCookie($cookie);
        }

        return $jsonResponse;
    }

    /**
     * Resend verification code.
     */
    public function resendVerificationCode(Request $request): JsonResponse
    {
        $user = $request->user();

        // We allow resend even if is_verified is true, because they might be verifying a new device (2FA).
        // The check.verified middleware ensures only users needing verification or 2FA hit this anyway.

        $user->sendVerificationEmail();

        return response()->json([
            'success' => true,
            'message' => __('messages.code_resent'),
        ]);
    }

    /**
     * Send a password reset link to the user's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Always return success to prevent user enumeration attacks
        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                ['token' => $token, 'created_at' => now()]
            );

            try {
                // Link to frontend Vue app
                $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
                $resetLink = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

                \Illuminate\Support\Facades\Mail::to($request->email)
                    ->queue(new \App\Mail\ResetPasswordCode($resetLink));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send reset link: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.reset_link_sent'),
        ]);
    }

    /**
     * Reset the password using the verified token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $resetData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetData) {
            return response()->json([
                'success' => false,
                'message' => __('messages.invalid_reset_link'),
            ], 422);
        }

        // Enforce 60-minute TTL on reset tokens
        if (\Carbon\Carbon::parse($resetData->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'message' => __('messages.reset_link_expired'),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => __('messages.user_not_found')], 404);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        // Cleanup token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.password_reset'),
        ]);
    }

    /**
     * Get the authenticated user's full profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Ensure profile exists (Lazy-initialization for legacy users)
        if (!$user->profile) {
            $displayName = $user->name ?? 'User';
            $user->profile()->create([
                'name' => $displayName,
                'username' => \App\Helpers\RestrictedNameHelper::generateUniqueHandle($displayName),
                'username_last_changed_at' => null,
            ]);
        }

        if (!$user->userStatus) {
            $user->userStatus()->create([]);
        }

        $user->load(['profile', 'roles', 'verification', 'userStatus']);

        $formattedUser = $this->formatUser($user);
        $token = $request->user()->currentAccessToken();
        if ($token && in_array('2fa-unverified', $token->abilities)) {
            $formattedUser['is_verified'] = false;
        }

        return response()->json([
            'success' => true,
            'user' => $formattedUser,
            'data' => $formattedUser, // Keep for backward compatibility if needed
        ]);
    }

    /**
     * Format user data for API responses.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'is_verified' => (bool) $user->is_verified,
            'is_badge_verified' => (bool) $user->is_badge_verified,
            'role' => $user->role,
            'roles' => $user->roles->pluck('name'),
            'storage_used' => $user->storage_used_bytes,
            'storage_limit' => $user->storage_limit_bytes,
            'is_public_profile' => (bool) $user->is_public_profile,
            'created_at' => $user->created_at,
        ];
    }
}
