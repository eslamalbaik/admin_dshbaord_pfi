<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    use ApiResponseTrait;
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if ($user->role === 'admin') {
                Log::info("Admin login: {$user->email} at " . now());
            }

            $deviceLabel = substr($request->header('User-Agent', 'Unknown Device'), 0, 120);
            $token = $user->createToken('auth_token', ['*'], now()->addDays(30));
            $token->accessToken->update(['device_label' => $deviceLabel]);
            $plainToken = $token->plainTextToken;

            $frontendUrl = rtrim(config('app.frontend_url'), '/');
            $landingUrl = rtrim(config('app.landing_url'), '/');

            $redirectUrl = match($user->role) {
                'admin', 'accountant' => $frontendUrl . '/dashboards',
                'student'             => $landingUrl . '/student/dashboard',
                default               => $landingUrl . '/',
            };

            return $this->successWithToken($plainToken, [
                'id'          => $user->id,
                'name'        => $user->name,
                'fullName'    => $user->name,
                'role'        => $user->role ?? 'student',
                'email'       => $user->email,
                'redirect_url'=> $redirectUrl,
            ], 'تم تسجيل الدخول بنجاح.', 200, 'user');
        }

        return $this->error('بيانات الاعتماد غير صحيحة.', 401, [
            'email' => ['invalid_credentials'],
        ], 'invalid_credentials');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $validated['email'] = strtolower(trim($validated['email']));

        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => 'student',
        ]);

        $plainToken = $user->createToken('auth_token')->plainTextToken;

        $landingUrl = rtrim(config('app.landing_url'), '/');

        return $this->successWithToken($plainToken, [
            'id'           => $user->id,
            'name'         => $user->name,
            'fullName'     => $user->name,
            'role'         => $user->role,
            'email'        => $user->email,
            'redirect_url' => $landingUrl . '/student/dashboard',
        ], 'تم إنشاء الحساب بنجاح.', 201, 'user');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            if ($user->role === 'admin') {
                Log::info("Admin logout: {$user->email} at " . now());
            }
            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }
        }

        return $this->success(message: 'تم تسجيل الخروج بنجاح.');
    }

    /**
     * Redirect to Google OAuth consent screen.
     * Google sends the user back to GOOGLE_REDIRECT_URI (frontend callback page).
     */
    public function redirectToGoogle(Request $request)
    {
        $landingUrl = rtrim(config('app.landing_url'), '/');

        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect($landingUrl . '/login?error=google_not_configured');
        }

        $returnTo = $request->query('return_to');

        // Store return_to in session so callback page can forward it
        session(['google_return_to' => $returnTo]);

        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    /**
     * POST /api/auth/google/exchange
     * Frontend callback page posts the OAuth code here.
     * We exchange it for a Socialite user and return a Sanctum token.
     */
    public function exchangeGoogleCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $landingUrl = rtrim(config('app.landing_url'), '/');

        try {
            $driver     = Socialite::driver('google')->stateless();
            $tokenData  = $driver->getAccessTokenResponse($request->input('code'));
            $googleUser = $driver->userFromToken($tokenData['access_token']);
        } catch (\Exception $e) {
            Log::warning('Google OAuth exchange failed: ' . $e->getMessage());
            return $this->error('فشل تسجيل الدخول عبر Google. حاول مرة أخرى.', 401);
        }

        if (empty($googleUser->getEmail())) {
            return $this->error('تعذّر الحصول على البريد الإلكتروني من حساب Google.', 422);
        }

        $email = strtolower(trim($googleUser->getEmail()));

        // Find existing user or create a new student account
        $user = \App\Models\User::withTrashed()->where('email', $email)->first();

        if ($user && $user->trashed()) {
            return $this->error('تم تعطيل هذا الحساب.', 403);
        }

        if (! $user) {
            $user = \App\Models\User::create([
                'name'     => $googleUser->getName() ?: $email,
                'email'    => $email,
                'password' => bcrypt(\Illuminate\Support\Str::random(32)), // random unusable password
                'role'     => 'student',
            ]);
            Log::info("New Google student registered: {$email}");
        }

        $plainToken = $user->createToken('google_oauth')->plainTextToken;

        $redirectUrl = in_array($user->role, ['admin', 'accountant'])
            ? rtrim(config('app.frontend_url'), '/') . '/dashboards'
            : $landingUrl . '/student/dashboard';

        return $this->successWithToken($plainToken, [
            'id'           => $user->id,
            'name'         => $user->name,
            'fullName'     => $user->name,
            'role'         => $user->role ?? 'student',
            'email'        => $user->email,
            'redirect_url' => $redirectUrl,
        ], 'تم تسجيل الدخول عبر Google بنجاح.', 200, 'user');
    }
}
