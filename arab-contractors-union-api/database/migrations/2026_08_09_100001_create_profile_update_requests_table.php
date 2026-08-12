<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-26: طلبات تعديل بيانات البروفايل الثانوية — لا تُكتب مباشرة على contractors،
 * تُطبَّق فقط عند موافقة الإدارة (approve). الحقول الأساسية (الاسم/رقم العضوية/رقم
 * المشتغل) مستثناة تماماً عبر whitelist صريح بالموديل، لا تظهر بـproposed_data إطلاقاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_update_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->json('proposed_data');
            $table->string('attachment')->nullable();
            $table->dateTime('phone_otp_verified_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('reject_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_update_requests');
    }
};
