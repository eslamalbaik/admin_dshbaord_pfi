<?php

use App\Models\Tender;
use Illuminate\Database\Migrations\Migration;

/** REQ-12: أي category حالي خارج قائمة الـ5 تصنيفات المعتمدة (+عام) يُنقل إلى "عام". */
return new class extends Migration
{
    public function up(): void
    {
        Tender::whereNotNull('category')
            ->whereNotIn('category', Tender::CATEGORIES)
            ->update(['category' => 'عام']);
    }

    public function down(): void
    {
        // لا رجوع — لا يمكن استرجاع التصنيفات الأصلية المستبدَلة.
    }
};
