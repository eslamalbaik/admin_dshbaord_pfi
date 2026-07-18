<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    // POST /api/v1/auth/login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة.',
                'errors'  => ['email' => ['The provided credentials do not match our records.']],
            ], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->role !== 'admin') {
            Auth::logout();
            return response()->json([
                'message' => 'Access denied. Admin privileges required.',
                'errors'  => ['email' => ['Access denied. Admin privileges required.']],
            ], 403);
        }

        // إلغاء الرموز القديمة وإنشاء رمز Sanctum جديد
        $user->tokens()->delete();
        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'fullName' => $user->name,
                'role'     => $user->role,
                'email'    => $user->email,
            ],
        ]);
    }

    // POST /api/v1/auth/logout
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    // GET /api/v1/auth/me
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id'       => $user->id,
            'fullName' => $user->name,
            'role'     => $user->role,
            'email'    => $user->email,
        ]);
    }
}
