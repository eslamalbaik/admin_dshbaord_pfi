<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // بادج معروض حقيقي (جديد/محدث/ينتهي قريباً/مغلق) — مستقل عن status الإداري
            // (مفتوحة/مغلقة/ملغية)، ومُدار عبر Tender::syncDisplayStatus() لا عبر المستخدم مباشرة
            $table->enum('display_status', ['new', 'updated', 'closing_soon', 'closed'])
                ->default('new')
                ->after('status');
        });

        DB::table('tenders')
            ->where(function ($q) {
                $q->where('status', 'closed')->orWhere('status', 'cancelled');
            })
            ->update(['display_status' => 'closed']);

        DB::table('tenders')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [now()->toDateString(), now()->addDays(4)->toDateString()])
            ->update(['display_status' => 'closing_soon']);

        DB::table('tenders')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->where(function ($q) {
                $q->whereNull('deadline')->orWhere('deadline', '>', now()->addDays(4)->toDateString());
            })
            ->whereColumn('updated_at', '>', 'created_at')
            ->where('updated_at', '>=', now()->subHours(48))
            ->update(['display_status' => 'updated']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('display_status');
        });
    }
};
