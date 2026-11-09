<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مراجعة تعديلات الملف التعريفي من التطبيق (Maker-Checker) + سجل تغييرات لكل حقل.
 *
 * profile_changes: سطر لكل حقل عدّله المقاول. نوعان:
 *   - pre_approval  — حقول قانونية ومستندات: القيمة الرسمية ما بتتغيّر لحد ما الإدارة تعتمد.
 *   - post_review   — بيانات تواصل: بتنطبّق فوراً، والإدارة بتأكّدها أو بترجّعها.
 *
 * contractor_field_history: أثر دائم لكل تغيير فعلي على بيانات المقاول من أي مصدر
 * (التطبيق، لوحة التحكم، النظام) — القيمة قبل وبعد ومين عمله.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('field', 64);
            $table->string('kind', 10)->default('text');          // text | file
            $table->string('mode', 16);                           // pre_approval | post_review
            $table->text('old_value')->nullable();                // القيمة الرسمية لحظة الإرسال
            $table->text('new_value')->nullable();                // القيمة المطلوبة (أو مسار الملف المؤقت)
            // pending | approved | rejected | withdrawn | superseded | confirmed | reverted
            $table->string('status', 16)->default('pending');
            $table->text('reject_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();        // المقاول أخفى إشعار الرفض
            $table->timestamps();

            $table->index(['contractor_id', 'status']);
            $table->index(['status', 'mode', 'created_at']);
        });

        Schema::create('contractor_field_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('field', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('source', 16);                         // app | dashboard | system
            $table->string('actor_type', 16)->nullable();         // contractor | user
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->foreignId('profile_change_id')->nullable()->constrained('profile_changes')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['contractor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_field_history');
        Schema::dropIfExists('profile_changes');
    }
};
