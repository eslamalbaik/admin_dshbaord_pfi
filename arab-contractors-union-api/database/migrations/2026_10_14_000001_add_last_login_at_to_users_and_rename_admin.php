<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. users.last_login_at — آخر دخول لحساب لوحة التحكم (بيظهر بالملف الشخصي).
 * 2. حساب الأدمن الأساسي كان اسمه عام ("Admin"...) والواجهة كانت تعرض بداله «اتحاد مقاولين العرب»؛
 *    صار الاسم الحقيقي محفوظ «اتحاد المقاولين الفلسطينيين» وبيقدر الأدمن يغيّره من ملفه.
 */
return new class extends Migration
{
    public const NEW_ADMIN_NAME = 'اتحاد المقاولين الفلسطينيين';

    private const GENERIC_ADMIN_NAMES = [
        'admin', 'admin user', 'prometrica admin', 'administrator', 'اتحاد مقاولين العرب',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_login_at')->nullable();
            });
        }

        DB::table('users')
            ->where('role', 'admin')
            ->where(function ($q) {
                $q->whereNull('name')->orWhere('name', '');
                foreach (self::GENERIC_ADMIN_NAMES as $name) {
                    $q->orWhereRaw('LOWER(TRIM(name)) = ?', [$name]);
                }
            })
            ->update(['name' => self::NEW_ADMIN_NAME]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('last_login_at');
            });
        }
    }
};
