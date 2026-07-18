<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        // الأعضاء الحاليون الذين يملكون كلمة مرور سجّلوا قبل إضافة خطوة التحقق،
        // نعتبر جوالهم مفعّلاً حتى لا يُمنعوا من الدخول
        DB::table('contractors')
            ->whereNotNull('password')
            ->update(['phone_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};
