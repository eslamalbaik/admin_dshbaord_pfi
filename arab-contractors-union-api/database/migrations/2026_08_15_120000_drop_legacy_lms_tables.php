<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط جداول نظام الـLMS القديم بعد حذف الكود المرتبط بها (فرع cleanup-lms-code).
 *
 * الترتيب مهم: الجداول التابعة أولاً ثم الأصلية، لأن بينها مفاتيح أجنبية.
 * تعطيل فحص المفاتيح أثناء الإسقاط يجعل العملية محصّنة ضد أي ترتيب متبقٍّ.
 *
 * لا يوجد down() يعيد البيانات — الجداول تُعاد فارغة فقط عبر الهجرات الأصلية
 * التي أُبقيت في مكانها لهذا الغرض تحديداً.
 */
return new class extends Migration
{
    /** التابعة قبل الأصلية */
    private array $tables = [
        'bundle_quiz_permissions',
        'bundle_digital_products',
        'bundle_course',
        'quiz_questions',
        'quiz_attempts',
        'options',
        'questions',
        'quizzes',
        'study_plan_tasks',
        'study_plans',
        'pdf_annotations',
        'product_purchases',
        'digital_product_files',
        'digital_products',
        'lesson_attachments',
        'lesson_comments',
        'lesson_user',
        'module_attachments',
        'course_modules',
        'lessons',
        'certificates',
        'enrollments',
        'course_reviews',
        'course_packages',
        'courses',
        'bundles',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * لا يُعاد إنشاء الجداول هنا — هجرات الـLMS الأصلية ما زالت موجودة،
     * فالتراجع يتم بتشغيلها لا بتكرار تعريفها في مكانين.
     */
    public function down(): void
    {
        // no-op بقصد
    }
};
