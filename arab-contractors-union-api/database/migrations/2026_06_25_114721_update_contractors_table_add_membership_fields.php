<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            if (! Schema::hasColumn('contractors', 'membership_number'))
                $table->string('membership_number')->unique()->nullable()->after('id');

            if (! Schema::hasColumn('contractors', 'is_frozen'))
                $table->boolean('is_frozen')->default(false)->after('status');

            if (! Schema::hasColumn('contractors', 'authorized_person'))
                $table->string('authorized_person')->nullable()->after('name');

            if (! Schema::hasColumn('contractors', 'commercial_register'))
                $table->string('commercial_register')->unique()->nullable()->after('authorized_person');

            if (! Schema::hasColumn('contractors', 'profile_completed'))
                $table->boolean('profile_completed')->default(false)->after('is_frozen');

            if (! Schema::hasColumn('contractors', 'profile_approved_by')) {
                $table->unsignedBigInteger('profile_approved_by')->nullable()->after('profile_completed');
                $table->foreign('profile_approved_by')->references('id')->on('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('contractors', 'fcm_token'))
                $table->string('fcm_token')->nullable()->after('profile_approved_by');

            if (! Schema::hasColumn('contractors', 'last_login_at'))
                $table->timestamp('last_login_at')->nullable()->after('fcm_token');

            if (! Schema::hasColumn('contractors', 'password'))
                $table->string('password')->nullable()->after('last_login_at');

            if (! Schema::hasColumn('contractors', 'remember_token'))
                $table->rememberToken()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropForeign(['profile_approved_by']);
            $table->dropColumn([
                'membership_number',
                'is_frozen',
                'authorized_person',
                'commercial_register',
                'profile_completed',
                'profile_approved_by',
                'fcm_token',
                'last_login_at',
                'password',
                'remember_token',
            ]);
        });
    }
};
