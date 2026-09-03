<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ملاحظة: بدون DB::transaction() هون عمداً — ALTER TABLE (auto_increment) تحت
        // بيسوي implicit commit بـMySQL، وبيكسر حالة الـtransaction wrapper لو كانت الجملتين
        // جوا نفس المعاملة. النسخ عبر chunkById دفعات صغيرة أصلاً، خطر جزئي فشل منخفض.
        DB::table('news')->where('category', 'event')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                $insert = $rows->map(fn ($n) => [
                    'id'               => $n->id,
                    'title'            => $n->title,
                    'slug'             => $n->slug,
                    'excerpt'          => $n->excerpt,
                    'body'             => $n->body,
                    'image'            => $n->image,
                    'video_url'        => $n->video_url,
                    'external_url'     => $n->external_url,
                    'gallery'          => $n->gallery,
                    'event_date'       => $n->event_date,
                    'event_location'   => $n->event_location,
                    'event_format'     => $n->event_format,
                    'is_international' => $n->is_international,
                    'stream_url'       => $n->stream_url,
                    'speakers'         => $n->speakers,
                    'is_published'     => $n->is_published,
                    'published_at'     => $n->published_at,
                    'created_by'       => $n->created_by,
                    'created_at'       => $n->created_at,
                    'updated_at'       => $n->updated_at,
                    'deleted_at'       => $n->deleted_at,
                ])->all();

                if (! empty($insert)) {
                    DB::table('events')->insert($insert);
                }
            });

        // احتياط: تأكيد إن الـauto_increment القادم ما يتصادم مع الـIDs المنسوخة صراحة
        $max = DB::table('events')->max('id');
        if ($max) {
            DB::statement('ALTER TABLE events AUTO_INCREMENT = ' . ((int) $max + 1));
        }
    }

    public function down(): void
    {
        // best-effort فقط — ما يُعتمد عليه لاسترجاع production، انظر ملاحظة الـrollback بالخطة
        DB::table('events')->orderBy('id')->chunkById(200, function ($rows) {
            $insert = $rows->map(fn ($e) => [
                'id'               => $e->id,
                'title'            => $e->title,
                'slug'             => $e->slug,
                'excerpt'          => $e->excerpt,
                'body'             => $e->body,
                'image'            => $e->image,
                'video_url'        => $e->video_url,
                'external_url'     => $e->external_url,
                'gallery'          => $e->gallery,
                'category'         => 'event',
                'event_date'       => $e->event_date,
                'event_location'   => $e->event_location,
                'event_format'     => $e->event_format,
                'is_international' => $e->is_international,
                'stream_url'       => $e->stream_url,
                'speakers'         => $e->speakers,
                'is_published'     => $e->is_published,
                'published_at'     => $e->published_at,
                'created_by'       => $e->created_by,
                'created_at'       => $e->created_at,
                'updated_at'       => $e->updated_at,
                'deleted_at'       => $e->deleted_at,
            ])->all();

            if (! empty($insert)) {
                DB::table('news')->insert($insert);
            }
        });
    }
};
