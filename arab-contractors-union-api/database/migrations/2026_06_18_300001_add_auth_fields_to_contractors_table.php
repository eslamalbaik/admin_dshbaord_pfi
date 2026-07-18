<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('password')->nullable()->after('phone');
            $table->rememberToken()->after('password');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('fcm_token')->nullable()->after('last_login_at'); // للإشعارات push
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token', 'last_login_at', 'fcm_token']);
        });
    }
};
