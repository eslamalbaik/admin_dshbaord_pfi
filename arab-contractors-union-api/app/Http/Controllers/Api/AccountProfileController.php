<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * الملف الشخصي لمستخدم لوحة التحكم (أدمن، محاسب، مشرف): عرض بيانات الحساب، تعديل الاسم
 * والصورة، وتغيير كلمة السر بعد تأكيد الحالية. كل واحد بيعدّل حسابه هو بس.
 */
class AccountProfileController extends Controller
{
    /** أقصى حجم للصورة بالكيلوبايت */
    public const AVATAR_MAX_KB = 2048;

    public const ROLE_LABELS = [
        'admin'      => 'مدير النظام',
        'accountant' => 'محاسب',
        'supervisor' => 'مشرف',
    ];

    // GET /api/v1/account/profile
    public function show(Request $request)
    {
        return response()->json(['data' => $this->format($request->user())]);
    }

    // POST /api/v1/account/profile  (multipart: name, avatar?, remove_avatar?)
    public function update(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|min:2|max:120',
            'avatar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:' . self::AVATAR_MAX_KB . '|dimensions:max_width=5000,max_height=5000',
            'remove_avatar' => 'nullable|boolean',
        ], [
            'name.required'     => 'الاسم مطلوب.',
            'name.min'          => 'الاسم قصير جداً.',
            'name.max'          => 'الاسم طويل جداً (120 حرف كحد أقصى).',
            'avatar.image'      => 'الملف لازم يكون صورة.',
            'avatar.mimes'      => 'الصورة لازم تكون JPG أو PNG أو WEBP.',
            'avatar.max'        => 'حجم الصورة لازم ما يزيد عن 2 ميجابايت.',
            'avatar.dimensions' => 'أبعاد الصورة كبيرة جداً (5000 بكسل كحد أقصى).',
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->name = trim($data['name']);

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($user);
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteAvatar($user);
            $user->avatar = null;
        }

        $user->save();

        return response()->json([
            'message' => 'تم حفظ الملف الشخصي.',
            'data'    => $this->format($user->fresh()),
        ]);
    }

    // POST /api/v1/account/password
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|max:100|confirmed|different:current_password',
        ], [
            'current_password.required' => 'أدخل كلمة السر الحالية.',
            'password.required'         => 'أدخل كلمة السر الجديدة.',
            'password.min'              => 'كلمة السر الجديدة لازم تكون 8 أحرف على الأقل.',
            'password.max'              => 'كلمة السر الجديدة طويلة جداً.',
            'password.confirmed'        => 'تأكيد كلمة السر غير مطابق.',
            'password.different'        => 'كلمة السر الجديدة لازم تختلف عن الحالية.',
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة السر الحالية غير صحيحة.'],
            ]);
        }

        $user->update(['password' => $data['password']]);

        // تسجيل الخروج من باقي الأجهزة، والجهاز الحالي بيضل داخل
        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();

        return response()->json(['message' => 'تم تغيير كلمة السر، وتم تسجيل الخروج من باقي الأجهزة.']);
    }

    private function deleteAvatar(User $user): void
    {
        if ($user->avatar && ! preg_match('#^https?://#', $user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
    }

    private function format(User $user): array
    {
        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'fullName'      => $user->name,
            'email'         => $user->email,
            'role'          => $user->role,
            'role_label'    => self::ROLE_LABELS[$user->role] ?? $user->role,
            'avatar'        => $user->avatar,
            'is_active'     => $user->is_active !== false,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'created_at'    => $user->created_at?->toISOString(),
            'permissions'   => $user->dashboardPermissions(),
            'permissions_count' => $user->isSupervisor() ? count($user->permissions ?? []) : null,
        ];
    }
}
