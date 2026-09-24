<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيفات العطاءات صارت قابلة للإدارة من لوحة التحكم (بدل Tender::CATEGORIES الثابتة)،
 * ولكل تصنيف صورة افتراضية واحدة تُعرض بدل صورة العطاء.
 *
 * tenders.category يبقى نصاً (اسم التصنيف) لا مفتاحاً أجنبياً عمداً — تطبيق المقاول وفلتر
 * "مجالاتي" وحمولة إشعار new_tender_published كلها تقرأ الاسم مباشرة. إعادة التسمية
 * تُعمَّم على tenders.category من TenderCategoryController::update.
 *
 * الصور القديمة كانت مخزّنة كـ Setting بمفتاح tender_category_image:{الاسم} — تُنقل هنا.
 */
return new class extends Migration
{
    private const SEED = ['مباني', 'طرق', 'بنية تحتية', 'قطاع صحي', 'قطاع تعليمي', 'عام'];

    public function up(): void
    {
        Schema::create('tender_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (self::SEED as $i => $name) {
            $key = 'tender_category_image:' . $name;

            DB::table('tender_categories')->insert([
                'name'       => $name,
                'image_path' => DB::table('settings')->where('key', $key)->value('value'),
                'is_active'  => true,
                'sort_order' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // أي تصنيف موجود فعلياً على عطاءات وخارج القائمة الأساسية يُضاف كتصنيف حتى لا يضيع
        $extra = DB::table('tenders')->whereNotNull('category')
            ->whereNotIn('category', self::SEED)->distinct()->pluck('category');

        foreach ($extra as $i => $name) {
            DB::table('tender_categories')->insert([
                'name'       => $name,
                'is_active'  => true,
                'sort_order' => count(self::SEED) + $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('settings')->where('key', 'like', 'tender_category_image:%')->delete();
        Cache::forget('settings.all');
    }

    public function down(): void
    {
        $now = now();

        foreach (DB::table('tender_categories')->whereNotNull('image_path')->get() as $row) {
            DB::table('settings')->insert([
                'key'        => 'tender_category_image:' . $row->name,
                'value'      => $row->image_path,
                'group'      => 'tenders',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        Cache::forget('settings.all');

        Schema::dropIfExists('tender_categories');
    }
};
