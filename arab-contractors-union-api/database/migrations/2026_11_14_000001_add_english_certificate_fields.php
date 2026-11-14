<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * شهادة العضوية بالإنجليزي (2026-11-14).
 *
 * - contractors.name_en / address_en: اسم الشركة وعنوانها بالإنجليزي — الأدمن بس بيدخّلهم من فورم المقاول.
 * - name_en على المجالات والاختصاصات وlabel_en على الدرجات: الترجمة اللي بتنطبع بجدول الشهادة.
 *   بنعبّي ترجمة للقيم الأساسية حسب الكود، والباقي بيتعبّى من صفحة "المجالات والاختصاصات".
 * - certificate_requests.certificate_path_en / certificate_address_en: ملف النسخة الإنجليزية
 *   (نفس الطلب ونفس الرقم التسلسلي) والعنوان الإنجليزي المُدخل عند الإصدار.
 */
return new class extends Migration
{
    private const FIELDS = [
        10 => 'Unspecified',
        20 => 'Roads',
        30 => 'Buildings',
        40 => 'Electromechanical',
        50 => 'Water & Sewage',
        60 => 'Public Works',
    ];

    private const SPECIALIZATIONS = [
        10  => 'Unspecified',
        20  => 'Roads',
        30  => 'Asphalt Mixes',
        40  => 'Bridges & Culverts Concrete',
        50  => 'Earthworks',
        60  => 'Buildings',
        70  => 'Precast Concrete',
        80  => 'Steel Structures',
        90  => 'Prefabricated Buildings',
        100 => 'Building Maintenance',
        110 => 'Electromechanical',
        120 => 'Electromechanical Maintenance',
        130 => 'Mechanical Works',
        140 => 'Electrical Works',
        150 => 'Electronics',
        160 => 'Water & Sewage',
        170 => 'Treatment Plants',
        180 => 'Irrigation & Drainage',
        190 => 'Excavation & Mining',
        200 => 'Public Works',
        210 => 'Railways',
        220 => 'Well Drilling',
    ];

    private const GRADES = [
        'اولى أ' => 'First A',
        'اولى ب' => 'First B',
        'اولى'   => 'First',
        'ثانية'  => 'Second',
        'ثالثة'  => 'Third',
        'رابعة'  => 'Fourth',
        'خامسة'  => 'Fifth',
    ];

    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('address_en')->nullable();
        });

        Schema::table('contractor_fields', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });
        Schema::table('contractor_specializations', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });
        Schema::table('contractor_grades', function (Blueprint $table) {
            $table->string('label_en')->nullable()->after('label');
        });

        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->string('certificate_path_en')->nullable()->after('certificate_path');
            $table->string('certificate_address_en')->nullable()->after('certificate_address');
        });

        foreach (self::FIELDS as $code => $name) {
            DB::table('contractor_fields')->where('code', $code)->whereNull('name_en')->update(['name_en' => $name]);
        }
        foreach (self::SPECIALIZATIONS as $code => $name) {
            DB::table('contractor_specializations')->where('code', $code)->whereNull('name_en')->update(['name_en' => $name]);
        }
        foreach (self::GRADES as $code => $label) {
            DB::table('contractor_grades')->where('code', $code)->whereNull('label_en')->update(['label_en' => $label]);
        }
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropColumn(['certificate_path_en', 'certificate_address_en']);
        });
        Schema::table('contractor_grades', fn (Blueprint $table) => $table->dropColumn('label_en'));
        Schema::table('contractor_specializations', fn (Blueprint $table) => $table->dropColumn('name_en'));
        Schema::table('contractor_fields', fn (Blueprint $table) => $table->dropColumn('name_en'));
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'address_en']);
        });
    }
};
