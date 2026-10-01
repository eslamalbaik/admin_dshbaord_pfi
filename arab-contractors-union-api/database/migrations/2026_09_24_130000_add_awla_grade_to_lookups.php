<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إضافة الدرجة "اولى" (الأولى غير المميّزة) إلى جدولي الدرجات والرسوم.
 *
 * السبب: نظام الاتحاد يمنح درجتَي "اولى أ" و"اولى ب" لتخصصات الأبنية والطرق والأشغال
 * العامة فقط. باقي التخصصات (صيانة الأبنية، المياه والمجاري، كهروميكانيك، كهرباء،
 * منشآت معدنية، حفر آبار، أشغال ترابية) تأخذ درجة "اولى" مجرّدة بلا تمييز أ/ب.
 * كشف 2026 المسدَّد يحوي 77 تصنيفاً بهذه الدرجة موزّعة على 48 شركة.
 *
 * لماذا الجدولان معاً: MembershipFeeCalculator يقرأ الرسوم بـ
 * `$gradeFees->get($grade)` من grade_fees — ودرجة موجودة في contractor_grades بلا
 * صف رسوم مقابل تجعل الحساب unresolvable. بدون هذه الهجرة تصبح رسوم 44 شركة
 * (23% من الكشف) غير قابلة للحساب، لأن "اولى" هي أعلى درجة في مجال لديها.
 *
 * level = 2 وليس 1 عمداً: لو ساوينا "اولى" بـ "اولى أ" (level 1) لتعادلتا داخل مجال
 * الأبنية، وصار ترتيب usort هو ما يحدّد أيّهما تُحتسب — فتنزل الرسوم من 600 إلى 400
 * دينار بصمت. المستوى 2 يجعل "اولى أ" تتقدّم دائماً حيث توجد، ويطابق رسوم "اولى ب".
 */
return new class extends Migration
{
    private const CODE = 'اولى';

    public function up(): void
    {
        $now = now();

        if (! DB::table('contractor_grades')->where('code', self::CODE)->exists()) {
            DB::table('contractor_grades')->insert([
                'code'                 => self::CODE,
                'label'                => 'الدرجة الأولى',
                'level'                => 2,
                'eligible_field_codes' => null, // لا تقييد بمجال — القيد على مستوى التخصص لا المجال
                'sort_order'           => 1,
                'is_active'            => true,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }

        if (! DB::table('grade_fees')->where('grade_code', self::CODE)->exists()) {
            DB::table('grade_fees')->insert([
                'grade_code'           => self::CODE,
                'grade_label'          => 'الدرجة الأولى',
                'sort_order'           => 2,
                'registration_fee_jod' => 500.00,
                'annual_fee_jod'       => 400.00,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }

        // ContractorLookups يخزّن الدرجات في الكاش — لا بد من إبطاله وإلا بقيت ست درجات
        cache()->forget('contractor_lookups.grades');
    }

    public function down(): void
    {
        $inUse = DB::table('contractors')
            ->whereNotNull('specialties')
            ->where('specialties', 'LIKE', '%"classification":"'.self::CODE.'"%')
            ->count();

        if ($inUse > 0) {
            throw new RuntimeException(
                'لا يمكن حذف درجة "'.self::CODE."\": ما زالت مستعملة لدى {$inUse} مقاول. "
                .'حوّل تصنيفاتهم لدرجة أخرى أولاً.'
            );
        }

        DB::table('grade_fees')->where('grade_code', self::CODE)->delete();
        DB::table('contractor_grades')->where('code', self::CODE)->delete();

        cache()->forget('contractor_lookups.grades');
    }
};
